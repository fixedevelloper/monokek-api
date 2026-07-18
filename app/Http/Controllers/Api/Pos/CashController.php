<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Services\CashSessionPrintService;
use App\Http\Services\PrintManagerService;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CashController extends Controller
{

public function storeRegister(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'branch_id' => 'required|exists:branches,id',
    ]);

    $register = CashRegister::create($validated);

    return response()->json([
        'message' => 'Caisse enregistrée avec succès',
        'register' => $register
    ], 201);
}
    /**
     * Vérifier si l'utilisateur a une session ouverte
     */
    public function status()
    {
        $session = CashSession::with('register')
            ->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        return response()->json([
            'is_open' => !!$session,
            'session' => $session
        ]);
    }

    /**
     * Ouvrir une nouvelle session de caisse
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function open(Request $request)
    {

        $request->validate([
            'register_id' => 'required|exists:cash_registers,id',
            'opening_amount' => 'required|numeric|min:0',
        ]);

        // Vérifier si une session est déjà ouverte
        $existing = CashSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Une caisse est déjà ouverte'], 422);
        }

        $session = CashSession::create([
            'register_id' => $request->register_id,
            'user_id' => auth()->id(),
            'opening_amount' => $request->opening_amount,
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caisse ouverte avec succès',
            'session' => $session->load('register')
        ], 201);
    }
       /**
     * Fermer la session de caisse (X-Report / Z-Report)
     */
    public function close(Request $request)
    {
        $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'note'           => 'nullable|string',
        ]);

        $session = CashSession::with('register.branch')->where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Aucune session active trouvée'], 404);
        }

        // 1. Calcul du montant théorique attendu
        $totalPayments = Payment::where('cash_session_id', $session->id)->sum('amount');
        $expectedAmount = $session->opening_amount + $totalPayments;

        $session->update([
            'closing_amount'  => $request->closing_amount,
            'expected_amount' => $expectedAmount,
            'closed_at'       => now(),
            'note'            => $request->note
        ]);

        // 2. Récupération du détail des paiements
        $paymentsDetail = Payment::where('cash_session_id', $session->id)
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->select('payment_methods.name', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('payment_methods.name')
            ->get()
            ->toArray();

        // 3. Récupération du résumé des articles vendus
        $soldItemsSummaryRaw = DB::table('order_items')
            ->join('order_rounds', 'order_items.order_round_id', '=', 'order_rounds.id')
            ->join('orders', 'order_rounds.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('payments', 'payments.order_id', '=', 'orders.id')
            ->where('payments.cash_session_id', $session->id)
            ->whereIn('orders.status', ['paid', 'completed'])
            ->select(
                'products.name as product_name',
                DB::raw('SUM(order_items.qty) as qty'),
                DB::raw('SUM(order_items.total) as total')
            )
            ->groupBy('products.id', 'products.name')
            ->get();

        $soldItemsSummary = json_decode(json_encode($soldItemsSummaryRaw), true);

        // 4. Ciblage de l'imprimante ticket active pour la branche
        $printer = Printer::where('branch_id', $session->register->branch_id ?? null)
            ->where('location', 'receipt')
            ->where('is_active', true)
            ->first();

        $printStatusMessage = "Aucune imprimante de caisse configurée.";

        if ($printer) {
            // Préparation du payload de données structuré pour ton PrintManagerService
            $sessionSummaryData = [
                'id'                     => $session->id,
                'cashier_name'           => auth()->user()->name ?? 'Caissier',
                'opened_at'              => $session->opened_at->toIso8601String(),
                'closed_at'              => $session->closed_at->toIso8601String(),
                'opening_balance'        => $session->opening_amount,
                'total_sales'            => $totalPayments,
                'expected_balance'       => $expectedAmount,
                'payment_methods_totals' => $paymentsDetail,
                'sold_items_summary'     => $soldItemsSummary
            ];

            // 5. Enregistrement direct dans ta table d'impression
            PrintQueue::create([
                'printer_id' => $printer->id,
                'job_type'       => 'session_summary', // Identifiant unique pour que ton worker sache quel template utiliser
                'content'    => $sessionSummaryData, // Laravel sérialisera automatiquement en JSON si ton modèle a le cast 'array' ou 'json'
                'status'     => 'pending',
            ]);

            $printStatusMessage = "Impression du rapport enregistrée dans la file d'attente.";
        }

        return response()->json([
            'message'       => 'Caisse fermée avec succès',
            'print_status'  => $printStatusMessage,
            'report'        => [
                'opened_at'       => $session->opened_at,
                'closed_at'       => $session->closed_at,
                'opening_amount'  => $session->opening_amount,
                'total_payments'  => $totalPayments,
                'expected_total'  => $expectedAmount,
                'actual_total'    => $request->closing_amount,
                'difference'      => $request->closing_amount - $expectedAmount,
                'note'            => $request->note,
                'payments_detail' => $paymentsDetail
            ]
        ]);
    }
    /**
     * Obtenir le récapitulatif actuel sans fermer (Z-Report)
     */
    public function currentSummary()
    {
        $session = CashSession::where('user_id', auth()->id())
            ->whereNull('closed_at')
            ->first();

        if (!$session) return response()->json(['message' => 'Caisse fermée'], 404);

        // Détail par méthode de paiement (Cash, MoMo, Orange, etc.)
        $paymentsByMethod = Payment::where('cash_session_id', $session->id)
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->select('payment_methods.name', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_methods.name')
            ->get();

        return response()->json([
            'session' => $session,
            'payments_detail' => $paymentsByMethod,
            'total_sales' => $paymentsByMethod->sum('total')
        ]);
    }
}
