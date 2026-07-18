<?php

namespace App\Http\Controllers\Api\Pos;

use App\Events\KitchenStationUpdated;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Events\RoundAddedToOrder;
use App\Events\RoundUpdated;
use App\Events\TicketCreated;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Services\CommissionService;
use App\Http\Services\PrintService;
use App\Http\Services\StockService;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\KitchenTicket;
use App\Models\ModifierItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRound;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{

    /**
     * ENVOI D'UN ROUND (Remplace ou complète requestBill)
     *
     * Corrections apportées vs. la version originale :
     *  1. Le prix des items/modifiers vient de la DB (Product / ModifierItem), plus du client.
     *  2. Validation complète du tableau modifiers.
     *  3. Si order_id est fourni mais invalide (payée / table différente / introuvable) -> 409, pas de fallback silencieux.
     *  4. Verrou pessimiste sur la table pour éviter la création de 2 commandes concurrentes.
     *  5. Verrou pessimiste sur la commande + calcul du round_number à l'intérieur du verrou.
     *  6. Les broadcast() sont déclenchés APRES le commit de la transaction (DB::afterCommit).
     *  7. Les items sans station de cuisine sont trackés et remontés dans la réponse (pas de perte silencieuse).
     *  8. Event distinct pour "round ajouté à commande existante" vs "commande créée".
     *  9. Préchargement des produits en une seule requête (fix N+1).
     * 10. Totaux arrondis explicitement.
     *
     * NB: adapte les noms de relations/colonnes (ex: ModifierItem::price) à ton schéma réel,
     *     je n'ai pas le modèle complet donc je pars sur des hypothèses raisonnables.
     *
     * @param Request $request
     * @param PrintService $printService
     * @return mixed
     */
    public function sendRound(Request $request, PrintService $printService)
    {
        $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'table_id' => 'required|exists:restaurant_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.modifier_item_id' => 'required_with:items.*.modifiers|exists:modifier_items,id',
            'items.*.modifiers.*.quantity' => 'nullable|integer|min:1',
            'note' => 'nullable|string',
        ]);
        // NB: on ne valide plus items.*.price / modifiers.*.price car on ne fait plus confiance
        // au prix envoyé par le client — voir étape 3 plus bas.

        $skippedItems = []; // items sans station cuisine (remontés en fin de réponse)
        $eventsToDispatch = []; // events différés jusqu'après le commit

        $result = DB::transaction(function () use ($printService, $request, &$skippedItems, &$eventsToDispatch) {

            // Verrou pessimiste sur la table pour éviter deux créations de commande concurrentes
            $table = RestaurantTable::with('floor')
                ->lockForUpdate()
                ->findOrFail($request->table_id);

            // 1. RÉCUPÉRER OU CRÉER LA COMMANDE
            $order = null;

            if ($request->filled('order_id')) {
                $order = Order::lockForUpdate()->find($request->order_id);

                if (!$order) {
                    abort(404, "Commande introuvable.");
                }
                if ($order->status === 'paid') {
                    abort(409, "Impossible d'ajouter un round : la commande est déjà payée.");
                }
                if ((int) $order->table_id !== (int) $table->id) {
                    abort(409, "Cette commande n'appartient pas à la table indiquée.");
                }
            }

            $isNewOrder = false;

            if (!$order) {
                // On protège aussi contre une commande "pending"/"active" déjà ouverte sur cette table
                // qu'on aurait dû réutiliser plutôt que d'en créer une nouvelle.
                $order = Order::where('table_id', $table->id)
                    ->where('status', '!=', 'paid')
                    ->lockForUpdate()
                    ->latest()
                    ->first();
            }

            if (!$order) {
                $order = Order::create([
                    'uuid' => (string) Str::uuid(),
                    'reference' => 'CMD-' . strtoupper(Str::random(8)),
                    'branch_id' => $table->floor->branch_id,
                    'table_id' => $table->id,
                    'user_id' => auth()->id(),
                    'cashier_id' => auth()->id(),
                    'status' => 'pending',
                    'type' => 'dinein',
                ]);
                $isNewOrder = true;
            }

            // 2. CRÉER LE ROUND (round_number calculé sous verrou -> pas de doublon en concurrence)
            $roundNumber = $order->rounds()->lockForUpdate()->count() + 1;
            $round = $order->rounds()->create([
                'round_number' => $roundNumber,
                'status' => 'sent',
                'note' => $request->note,
                'sent_at' => now(),
            ]);

            // 3. PRÉCHARGER LES PRODUITS (fix N+1) + tous les modifier_items en une passe
            $productIds = collect($request->items)->pluck('product_id')->unique();
            $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

            $modifierIds = collect($request->items)
                ->pluck('modifiers')
                ->filter()
                ->flatten(1)
                ->pluck('modifier_item_id')
                ->unique();
            $modifierItems = $modifierIds->isNotEmpty()
                ? ModifierItem::whereIn('id', $modifierIds)->get()->keyBy('id')
                : collect();

            $currentRoundItems = collect();

            // 4. CRÉER LES ITEMS DU ROUND — prix pris depuis la DB, jamais depuis le client
            foreach ($request->items as $itemData) {
                $product = $products->get($itemData['product_id']);
                if (!$product) {
                    abort(422, "Produit introuvable : {$itemData['product_id']}");
                }

                $unitPrice = $product->price; // source de vérité = DB
                $qty = $itemData['qty'];

                $orderItem = $round->items()->create([
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'price' => $unitPrice,
                    'total' => round($unitPrice * $qty, 2),
                    'status' => 'pending',
                ]);

                $orderItem->setRelation('product', $product);
                $currentRoundItems->push($orderItem);

                if (!empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $mod) {
                        $modifierItem = $modifierItems->get($mod['modifier_item_id']);
                        if (!$modifierItem) {
                            abort(422, "Modifier introuvable : {$mod['modifier_item_id']}");
                        }

                        $orderItem->modifiers()->create([
                            'modifier_item_id' => $modifierItem->id,
                            'price' => $modifierItem->price, // source de vérité = DB
                            'quantity' => $mod['quantity'] ?? 1,
                        ]);
                    }
                }
            }

            // 5. CRÉER LES TICKETS DE CUISINE (uniquement pour les items de CE round)
            $itemsByStation = $currentRoundItems->groupBy(
                fn($item) => $item->product->category?->kitchen_station_id
        );

        foreach ($itemsByStation as $stationId => $items) {
            if ($stationId) {
                $ticket = KitchenTicket::create([
                    'order_id' => $order->id,
                    'order_round_id' => $round->id,
                    'station_id' => $stationId,
                    'status' => 'pending',
                ]);

                // broadcast différé après le commit, cf. étape 8
                $eventsToDispatch[] = new TicketCreated($ticket);
            } else {
                // Ne PAS ignorer silencieusement : on trace les items sans station
                foreach ($items as $item) {
                    $skippedItems[] = [
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? null,
                        'reason' => 'Aucune station de cuisine définie pour la catégorie de ce produit.',
                    ];
                }
            }
        }

        // 6. MISE À JOUR DES TOTAUX
        $order->refreshTotals();

        // 7. MISE À JOUR DE LA TABLE
        $table->update(['status' => 'occupied']);

        // 8. IMPRESSION (on garde ça dans la transaction : si l'impression échoue,
        //    à toi de voir si tu veux rollback ou juste logger — ici on suppose
        //    que queueRoundTickets ne fait qu'empiler un job, donc pas de risque).
        $printService->queueRoundTickets($round);

        // Event distinct : round ajouté à une commande existante VS nouvelle commande créée
        $eventsToDispatch[] = $isNewOrder
            ? new OrderCreated($order->load(['rounds.items', 'rounds.items.product']))
            : new RoundAddedToOrder($order->load(['rounds.items', 'rounds.items.product']), $round);

        return $order->load(['rounds.items.product', 'table', 'rounds.kitchenTickets']);
    });

        // 9. Broadcast SEULEMENT après commit réussi de la transaction
        foreach ($eventsToDispatch as $event) {
            broadcast($event)->toOthers();
        }

        return (new OrderResource($result))
            ->additional([
                'meta' => [
                    'skipped_kitchen_items' => $skippedItems,
                ],
            ]);
    }
    /**
     * FINALISER LE PAIEMENT (Inchangé sur la logique, mais refresh sur les relations)
     */
    public function finalizePayment(Request $request, $uuid, CommissionService $commissionService, PrintService $printService)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,momo,orange,card',
            'amount_received' => 'required|numeric'
        ]);

        return DB::transaction(function () use ($printService, $commissionService, $request, $uuid) {
            $session = CashSession::where('user_id', auth()->id())->whereNull('closed_at')->first();
            if (!$session) return response()->json(['error' => 'Caisse fermée'], 403);

            $order = Order::where('uuid', $uuid)->firstOrFail();

            // Paiement
            $payMethod = PaymentMethod::where('name', $request->payment_method)->first();
            $order->payments()->create([
                'cash_session_id' => $session->id,
                'payment_method_id' => $payMethod->id,
                'amount' => $order->total,
                'amount_received' => $request->amount_received,
                'change_due' => $request->amount_received - $order->total,
            ]);

            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $order->table()->update(['status' => 'free']);

            $commissionService->calculateCommissions($order);

            // Impression du ticket final (Reçu client complet)
            $printService->queueFinalReceipt($order);
            broadcast(new OrderStatusUpdated($order->load(['rounds.items','rounds.items.product','table'])))->toOthers();
            return response()->json(['message' => 'Payé', 'status' => 'success']);
        });
    }

    /**
     * Historique des commandes payées
     * @param Request $request
     * @return
     */
    public function history(Request $request)
    {
        $user = $request->user();

        // 1. Récupérer la session active de l'utilisateur
        $session = CashSession::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return Helpers::error("Session de caisse introuvable.");
        }

        // 2. Récupérer les commandes avec les Rounds et leurs Items
        $orders = Order::with([
            'table',
            'user',
            'rounds' => function($query) {
                $query->orderBy('round_number', 'asc');
            },
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
            // On garde items à la racine au cas où (pour les totaux rapides),
            // mais le front utilisera surtout rounds.items
            'items.product'
        ])
            ->where(function ($query) use ($session) {
                // Commandes payées dans cette session
                $query->whereHas('payments', function ($q) use ($session) {
                    $q->where('cash_session_id', $session->id);
                })
                    // OU commandes actives (non annulées et non payées) du jour
                    ->orWhere(function($q) {
                        $q->whereNotIn('status', ['cancelled', 'paid'])
                            ->where('created_at', '>=', now()->startOfDay());
                    });
            })
            ->latest()
            ->paginate(50); // Augmenté à 50 pour une meilleure visibilité sur le shift

        return Helpers::success(OrderResource::collection($orders));
    }

    public function historyAdmin(Request $request)
    {
        // On charge les rounds et les caissiers/serveurs pour une traçabilité totale
        $query = Order::with([
            'table',
            'user',
            'cashier',
            'rounds' => function($q) {
                $q->orderBy('round_number', 'asc');
            },
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
            // On garde aussi les items à plat si besoin de calculs directs
            'items.product'
        ]);

        // Filtre par date (Y-m-d)
        if($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date.' 00:00:00',
                $request->end_date.' 23:59:59'
            ]);
        }

        // Filtre par recherche (Référence ou Table)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('table', function ($t) use ($search) {
                        $t->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filtre par statut (Optionnel mais utile pour un Admin)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(50);

        return Helpers::success(OrderResource::collection($orders));
    }

    public function index(Request $request)
    {
        $query = Order::query()->with([
            'table',
            'user',
            'rounds' => function ($q) {
                $q->orderBy('round_number', 'asc');
            },
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
        ]);

        // Filtre par recherche (Référence ou Table)
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%")
                    ->orWhereHas('table', function ($sq) use ($request) {
                        $sq->where('name', 'like', "%{$request->search}%");
                    });
            });
        }

        // Filtre par Date Spécifique
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filtre par Statut
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15);

        return OrderResource::collection($orders);
    }

    public function getActiveOrder(RestaurantTable $table)
    {
        // On cherche la commande active
        $order = Order::where('table_id', $table->id)
            ->whereIn('status', ['pending', 'processing', 'partially_paid', 'pending_payment', 'billing','completed'])
            ->with([
                // On charge les rounds triés par ordre de création
                'rounds' => function($query) {
                    $query->orderBy('round_number', 'asc');
                },
                // On charge les détails à l'intérieur de chaque round
                'rounds.items.product',
                'rounds.items.modifiers.modifierItem',
                // On peut aussi garder les relations de base de la commande
                'table',
                'user'
            ])
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Aucune commande active pour cette table',
                'data' => null
            ], 200);
        }

        // Important : Assure-toi que ton OrderResource traite bien la collection "rounds"
        return new OrderResource($order);
    }

    public function waiterOrders(Request $request)
    {
        $query = Order::with([
            'table',
            'user',
            'items.product',
            'items.variant',            // Ajouté
            'items.modifiers.modifierItem' // Ajouté (charge les modifiers ET leur définition/nom)
        ])
            ->where('user_id', auth()->id());

        // Filtre par date
        $date = $request->input('date', now()->today());
        $query->whereDate('created_at', $date);

        // Filtre par statut
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return OrderResource::collection($query->latest()->get());
    }

    public function markAsServed(Order $order)
    {
        // 1. On met à jour le statut de l'ordre
        $order->update(['status' => 'completed']);

        // 2. On met à jour tous les tickets de cuisine liés à 'served'
        $order->kitchenTickets()->update(['kitchen_tickets.status' => 'served']);

        broadcast(new OrderStatusUpdated($order->load(['rounds.items','rounds.items.product','table'])))->toOthers();

        return response()->json(['message' => 'Commande servie !']);
    }
    public function reprint(Order $order, PrintService $printService)
    {
        // Impression manuelle (via votre bouton React)
        $printService->queueFinalReceipt($order);

        return response()->json(['message' => 'Impression lancée']);
    }



    /**
     * MÉTHODES À AJOUTER AU CONTRÔLEUR (à côté de sendRound()).
     *
     * Règle métier : un round ne peut être modifié QUE si son status est 'sent'
     * (pas encore 'served') ET que la commande n'est pas 'paid'. Dès qu'un round
     * passe à 'served', ou que la commande est payée, ces routes doivent 409.
     *
     * NB: adapte le nom du status "servi" si ce n'est pas 'served' chez toi
     * (je me suis basé sur le front qui affiche round.status === 'sent' ? '⏳' : '✅ Servi').
     */

    /**
     * Garde commune : lève une exception si le round n'est plus modifiable.
     * @param OrderRound $round
     */
    private function assertRoundIsEditable(OrderRound $round): void
    {
        if ($round->status !== 'sent') {
            abort(409, "Ce round ne peut plus être modifié (déjà servi ou annulé).");
        }
        if ($round->order->status === 'paid') {
            abort(409, "Impossible de modifier : la commande est déjà payée.");
        }
    }

    /**
     * MODIFIER LA QUANTITÉ D'UN ITEM DANS UN ROUND (ou le supprimer si qty=0).
     * PATCH /api/pos/rounds/{round}/items/{item}
     * @param Request $request
     * @param OrderRound $round
     * @param OrderItem $item
     * @param PrintService $printService
     * @return mixed
     */
    public function updateRoundItemQty(
        Request $request,
        OrderRound $round,
        OrderItem $item,
        PrintService $printService
    )
    {
        $request->validate([
            'qty' => 'required|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $round, $item, $printService) {
            $round = OrderRound::with('order')->lockForUpdate()->findOrFail($round->id);
            $this->assertRoundIsEditable($round);

            if ((int)$item->order_round_id !== (int)$round->id) {
                abort(404, "Cet article n'appartient pas à ce round.");
            }

            $newQty = (int)$request->qty;
            $previousQty = $item->qty;

            if ($newQty === $previousQty) {
                // Rien à faire, on évite un round-trip cuisine inutile
                return new OrderResource(
                    $round->order->fresh()->load(['rounds.items.product', 'rounds.items.modifiers.modifierItem', 'table'])
                );
            }

            if ($newQty === 0) {
                $item->modifiers()->delete();
                $item->delete();
            } else {
                $item->update([
                    'qty' => $newQty,
                    'total' => round($item->price * $newQty, 2),
                ]);

                // Si la quantité AUGMENTE, il faut renvoyer le supplément en cuisine
                // (sinon le cuisinier ne sait pas qu'il doit en préparer plus).
                if ($newQty > $previousQty) {
                    $this->notifyKitchenOfQuantityIncrease($round, $item, $newQty - $previousQty);
                }
                // Si la quantité DIMINUE, on ne notifie pas la cuisine automatiquement :
                // à toi de voir si tu veux un event "annulation partielle" séparé,
                // ex. si le client se rétracte après que le plat soit déjà en préparation.
            }

            $round->order->refreshTotals();

            $updatedOrder = $round->order->fresh()->load([
                'rounds.items.product',
                'rounds.items.modifiers.modifierItem',
                'table',
            ]);

            broadcast(new RoundUpdated($updatedOrder, $round->fresh()))->toOthers();

            return new OrderResource($updatedOrder);
        });
    }

    /**
     * AJOUTER UN NOUVEL ITEM À UN ROUND DÉJÀ ENVOYÉ (tant qu'il n'est pas servi).
     * POST /api/pos/rounds/{round}/items
     * @param Request $request
     * @param OrderRound $round
     * @param PrintService $printService
     * @return mixed
     */
    public function addItemToRound(
        Request $request,
        OrderRound $round,
        PrintService $printService
    )
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'modifiers' => 'nullable|array',
            'modifiers.*.modifier_item_id' => 'required_with:modifiers|exists:modifier_items,id',
            'modifiers.*.quantity' => 'nullable|integer|min:1',
        ]);

        return DB::transaction(function () use ($request, $round, $printService) {
            $round = OrderRound::with('order')->lockForUpdate()->findOrFail($round->id);
            $this->assertRoundIsEditable($round);

            $product = Product::with('category')->findOrFail($request->product_id);

            $orderItem = $round->items()->create([
                'product_id' => $product->id,
                'qty' => $request->qty,
                'price' => $product->price,
                'total' => round($product->price * $request->qty, 2),
                'status' => 'pending',
            ]);
            $orderItem->setRelation('product', $product);

            if (!empty($request->modifiers)) {
                $modifierIds = collect($request->modifiers)->pluck('modifier_item_id');
                $modifierItems = ModifierItem::whereIn('id', $modifierIds)->get()->keyBy('id');

                foreach ($request->modifiers as $mod) {
                    $modifierItem = $modifierItems->get($mod['modifier_item_id']);
                    if (!$modifierItem) {
                        abort(422, "Modifier introuvable : {$mod['modifier_item_id']}");
                    }
                    $orderItem->modifiers()->create([
                        'modifier_item_id' => $modifierItem->id,
                        'price' => $modifierItem->price,
                        'quantity' => $mod['quantity'] ?? 1,
                    ]);
                }
            }

            // Ticket cuisine pour ce nouvel item uniquement
            $stationId = $product->category ?->kitchen_station_id;
        if ($stationId) {
            $ticket = KitchenTicket::create([
                'order_id' => $round->order_id,
                'order_round_id' => $round->id,
                'station_id' => $stationId,
                'status' => 'pending',
            ]);
            broadcast(new TicketCreated($ticket))->toOthers();
        }

        $printService->queueRoundTickets($round->fresh());

        $round->order->refreshTotals();

        $updatedOrder = $round->order->fresh()->load([
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
            'table',
        ]);

        broadcast(new RoundUpdated($updatedOrder, $round->fresh()))->toOthers();

        return new OrderResource($updatedOrder);
    });
    }

    /**
     * Notifie la cuisine d'une augmentation de quantité sur un item déjà envoyé.
     * Simplification : on crée un "mini ticket" pour la quantité supplémentaire
     * uniquement, sans dupliquer tout l'item original.
     */
    private function notifyKitchenOfQuantityIncrease(OrderRound $round, OrderItem $item, int $extraQty): void
    {
        $stationId = $item->product->category ?->kitchen_station_id;
    if (!$stationId) {
        return;
    }

    $ticket = KitchenTicket::create([
        'order_id' => $round->order_id,
        'order_round_id' => $round->id,
        'station_id' => $stationId,
        'status' => 'pending',
        'note' => "Supplément : +{$extraQty} {$item->product->name}",
    ]);

    broadcast(new TicketCreated($ticket))->toOthers();
}
}
