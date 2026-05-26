<?php


namespace App\Http\Services;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesSummaryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $start;
    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    public function query()
    {
        // On groupe le Chiffre d'Affaires jour par jour
        return Order::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$this->start, $this->end])
            ->selectRaw('DATE(created_at) as date, COUNT(id) as total_orders, SUM(total) as daily_ttc')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function headings(): array
    {
        return [
            'Date Operation',
            'Nombre de Commandes',
            'CA HT (FCFA)',
            'TVA Collectée (19.25%)', // Exemple taux Cameroun si applicable
            'CA TTC Total (FCFA)'
        ];
    }

    public function map($row): array
    {
        $tva=1.1925;
        // Calculs comptables basiques (Exemple avec TVA à 19.25% incluse, ou 0 si non applicable)
        $ttc = (float) $row->daily_ttc;
        $ht = $ttc;
        $tva = $ttc - $ht;

        return [
            $row->date,
            $row->total_orders,
            round($ht, 2),
            round($tva, 2),
            $ttc
        ];
    }
}
