<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Rapport Flash (Tableau de bord principal)
     */
    public function dashboardStats()
    {
        $today = Carbon::today();

        // 1. Chiffre d'affaires du jour (Total, Cash, Mobile Money)
        $sales = Order::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->select(
                DB::raw('SUM(total_amount) as total'),
                DB::raw("SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as total_cash"),
                DB::raw("SUM(CASE WHEN payment_method IN ('momo', 'orange') THEN total_amount ELSE 0 END) as total_mobile")
            )
            ->first();

        // 2. Nombre de couverts / commandes
        $orderCount = Order::whereDate('created_at', $today)->count();

        // 3. Top 5 des produits les plus vendus aujourd'hui
        $topProducts = OrderItem::whereDate('created_at', $today)
            ->select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        return response::json([
            'date' => $today->toDateString(),
            'revenue' => [
                'total' => (float) ($sales->total ?? 0),
                'cash' => (float) ($sales->total_cash ?? 0),
                'mobile_money' => (float) ($sales->total_mobile ?? 0),
            ],
            'orders_count' => $orderCount,
            'top_products' => $topProducts,
        ]);
    }

    /**
     * Ventes par catégorie (Pour identifier ce qui marche le mieux)
     */
    public function salesByCategory(Request $request)
    {
        $start = $request->input('start_date', Carbon::now()->startOfMonth());
        $end = $request->input('end_date', Carbon::now()->endOfMonth());

        $stats = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->select('categories.name', DB::raw('SUM(order_items.subtotal) as total_sales'))
            ->groupBy('categories.name')
            ->orderByDesc('total_sales')
            ->get();

        return response()->json($stats);
    }

    /**
     * Rapport de clôture de caisse (Rapport X / Z)
     */
    public function closingReport()
    {
        $user = auth()->user();
        $today = Carbon::today();

        // On regroupe tout ce qu'un caissier a encaissé durant son shift
        $report = Order::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->where('status', 'completed')
            ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(total_amount) as total'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'cashier' => $user->name,
            'shift_date' => $today->toDateTimeString(),
            'breakdown' => $report
        ]);
    }
    public function getAnalytics(Request $request)
    {
        // 1. Validation et Définition de la période unique
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // On utilise les dates de la requête ou les 30 derniers jours par défaut
        $start = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        // 2. Chiffre d'Affaires et KPIs (Basé sur la table payments pour la précision)
        $totalSales = Payment::whereBetween('created_at', [$start, $end])->sum('amount');
        $orderCount = Order::whereBetween('created_at', [$start, $end])->count();
        $averageCart = $orderCount > 0 ? $totalSales / $orderCount : 0;

        // 3. Flux Horaire
        $hourlyFlow = Order::whereBetween('created_at', [$start, $end])
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // 4. Performance Serveurs (Lien user_id sur orders)
        $waiterPerformance = Order::join('users', 'orders.user_id', '=', 'users.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->select('users.name', DB::raw('SUM(orders.total) as sales'), DB::raw('COUNT(orders.id) as orders'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('sales')
            ->take(5)
            ->get();

        // 5. Répartition des Modes de Paiement
        $payments = DB::table('payments')
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('payments.created_at', [$start, $end])
            ->select(
                'payment_methods.name as method_name',
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->get();

        // 6. Évolution des ventes (Graphique linéaire)
        $salesOverTime = Order::where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 7. Top 5 des produits les plus vendus
        $topProducts = OrderItem::join('products', 'order_items.product_id', '=', 'products.id')
            // 1. On passe d'abord de l'item vers son round (ajoute le bon nom de colonne : round_id ou order_round_id)
            ->join('order_rounds', 'order_items.order_round_id', '=', 'order_rounds.id')
            // 2. On lie ensuite le round à la commande globale
            ->join('orders', 'order_rounds.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.status', 'paid')
            ->select(
                'products.name',
                DB::raw('SUM(order_items.qty) as qty'),
                DB::raw('SUM(order_items.total) as revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('qty')
            ->take(5)
            ->get();

        // 8. Retour JSON unique et cohérent
        return response()->json([
            'summary' => [
                'period_label' => "Du {$start->format('d/m')} au {$end->format('d/m/Y')}"
            ],
            'kpis' => [
                'total_sales' => (float) $totalSales,
                'orders_count' => $orderCount,
                'average_cart' => (float) $averageCart,
                'food_cost' => 32, // À dynamiser plus tard si nécessaire
            ],
            'hourly_flow' => $hourlyFlow,
            'waiters' => $waiterPerformance,
            'payments' => $payments,
            'chart_data' => $salesOverTime,
            'top_products' => $topProducts
        ]);
    }
}
