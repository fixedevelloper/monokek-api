<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\SalesSummaryExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Payment;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf; // Si tu utilises barryvdh/laravel-dompdf pour les PDF
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    /**
     * Parse et valide la période demandée par le comptable
     */
    private function getPeriod(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'required|in:excel,pdf'
        ]);

        return [
            Carbon::parse($request->start_date)->startOfDay(),
            Carbon::parse($request->end_date)->endOfDay()
        ];
    }

    /**
     * 1. Journal Général des Ventes
     */
    public function salesSummary(Request $request)
    {
        [$start, $end] = $this->getPeriod($request);

        if ($request->format === 'excel') {
            return Excel::download(new SalesSummaryExport($start, $end), 'journal_ventes.xlsx');
        }

        // Option PDF
        $data = Order::where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(id) as count, SUM(total) as total')
            ->groupBy('date')
            ->get();

        $pdf = Pdf::loadView('pdf.accounting.sales_summary', compact('data', 'start', 'end'));
        return $pdf->download('journal_ventes.pdf');
    }

    /**
     * 2. Rapport des Règlements (Modes de paiement)
     */
    public function paymentsBreakdown(Request $request)
    {
        [$start, $end] = $this->getPeriod($request);

        // Extraction brute des règlements ventilés par jour et par méthode pour le lettrage
        $payments = Payment::join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('payments.created_at', [$start, $end])
            ->select(
                DB::raw('DATE(payments.created_at) as date'),
                'payment_methods.name as method',
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('date', 'payment_methods.name')
            ->orderBy('date')
            ->get();

        if ($request->format === 'excel') {
            // Tu peux créer un PaymentBreakdownExport similaire au premier
            // Pour faire court ici, on peut utiliser une collection rapide inline :
            return Excel::download(new class($payments) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function collection() { return $this->data; }
                public function headings(): array { return ['Date', 'Mode de Règlement', 'Montant (FCFA)']; }
            }, 'ventilation_reglements.xlsx');
        }

        $pdf = Pdf::loadView('pdf.accounting.payments', compact('payments', 'start', 'end'));
        return $pdf->download('ventilation_reglements.pdf');
    }

    /**
     * 3. Journal Détaillé des Factures
     */
    public function detailedSales(Request $request)
    {
        [$start, $end] = $this->getPeriod($request);

        $orders = Order::with(['user', 'cashier'])
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        if ($request->format === 'excel') {
            return Excel::download(new class($orders) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithMapping {
                private $orders;
                public function __construct($orders) { $this->orders = $orders; }
                public function collection() { return $this->orders; }
                public function headings(): array { return ['Date & Heure', 'Référence', 'Serveur', 'Caissier', 'Total TTC']; }
                public function map($order): array {
                    return [
                        $order->created_at->format('d/m/Y H:i'),
                        $order->reference,
                        $order->user?->name ?? 'N/A',
                        $order->cashier?->name ?? 'N/A',
                        $order->total
                    ];
                }
            }, 'journal_detaille_factures.xlsx');
        }

        $pdf = Pdf::loadView('pdf.accounting.detailed_sales', compact('orders', 'start', 'end'));
        return $pdf->download('journal_detaille_factures.pdf');
    }

    /**
     * 4. Ventes par Catégorie de Produits
     */
    public function categoriesSales(Request $request)
    {
        [$start, $end] = $this->getPeriod($request);

        // Jointure à travers les ord_items -> order_rounds (ou rounds) -> orders pour respecter ton architecture
        $categories = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('order_rounds', 'order_items.order_round_id', '=', 'order_rounds.id')
            ->join('orders', 'order_rounds.order_id', '=', 'orders.id')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$start, $end])
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(order_items.qty) as total_qty'),
                DB::raw('SUM(order_items.total) as total_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        if ($request->format === 'excel') {
            return Excel::download(new class($categories) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function collection() { return $this->data; }
                public function headings(): array { return ['Catégorie', 'Quantité Vendue', 'Chiffre d\'Affaires (FCFA)']; }
            }, 'ventilation_categories.xlsx');
        }

        $pdf = Pdf::loadView('pdf.accounting.categories', compact('categories', 'start', 'end'));
        return $pdf->download('ventilation_categories.pdf');
    }
}
