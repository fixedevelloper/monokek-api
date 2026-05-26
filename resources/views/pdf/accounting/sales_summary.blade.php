@extends('pdf.accounting.layout')

@section('title', 'Journal des Ventes')
@section('report_name', '1. Journal Général des Ventes (Z Cumulés)')

@section('content')
    <table>
        <thead>
        <tr>
            <th>Date Opération</th>
            <th class="text-center">Commandes Closes</th>
            <th class="text-right">CA HT (FCFA)</th>
            <th class="text-right">TVA Collectée (19.25%)</th>
            <th class="text-right">CA TTC Total (FCFA)</th>
        </tr>
        </thead>
        <tbody>
        @php $grandTotalTTC = 0; @endphp
        @foreach($data as $row)
            @php
                $ht = $row->total / 1.1925;
                $tva = $row->total - $ht;
                $grandTotalTTC += $row->total;
            @endphp
            <tr>
                <td class="font-bold">{{ Carbon\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                <td class="text-center">{{ $row->count }}</td>
                <td class="text-right">{{ number_format($ht, 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($tva, 2, ',', ' ') }}</td>
                <td class="text-right font-bold">{{ number_format($row->total, 2, ',', ' ') }}</td>
            </tr>
        @endforeach
        <tr style="background-color: #f8fafc;">
            <td colspan="2" class="font-bold" style="font-size: 11px; text-transform: uppercase;">Total Général Période</td>
            <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($grandTotalTTC / 1.1925, 2, ',', ' ') }}</td>
            <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($grandTotalTTC - ($grandTotalTTC / 1.1925), 2, ',', ' ') }}</td>
            <td class="text-right font-bold" style="font-size: 12px; color: #2563eb;">{{ number_format($grandTotalTTC, 2, ',', ' ') }} F</td>
        </tr>
        </tbody>
    </table>
@endsection
