@extends('pdf.accounting.layout')

@section('title', 'Journal des Factures Closes')
@section('report_name', '3. Registre Nominatif et Unitaire des Factures')

@section('content')
    <table>
        <thead>
        <tr>
            <th>Date / Heure</th>
            <th>Référence Unique</th>
            <th>Serveur</th>
            <th>Caissier</th>
            <th class="text-right">Net à Payer (TTC)</th>
        </tr>
        </thead>
        <tbody>
        @php $grandTotal = 0; @endphp
        @foreach($orders as $order)
            @php $grandTotal += $order->total; @endphp
            <tr>
                <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                <td class="font-bold" style="color: #0f172a;">{{ $order->reference }}</td>
                <td>{{ $order->waiter?->name ?? 'N/A' }}</td>
                <td>{{ $order->cashier?->name ?? 'Système' }}</td>
                <td class="text-right font-bold">{{ number_format($order->total, 0, ',', ' ') }} F</td>
            </tr>
        @endforeach
        <tr style="background-color: #f8fafc;">
            <td colspan="4" class="font-bold" style="text-transform: uppercase;">Cumul Comptable unitaire</td>
            <td class="text-right font-bold" style="font-size: 11px; color: #2563eb;">{{ number_format($grandTotal, 0, ',', ' ') }} F</td>
        </tr>
        </tbody>
    </table>
@endsection
