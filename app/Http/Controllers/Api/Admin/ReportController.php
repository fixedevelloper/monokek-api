<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
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
}