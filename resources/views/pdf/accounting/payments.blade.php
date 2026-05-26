@extends('pdf.accounting.layout')

@section('title', 'Ventilation des Règlements')
@section('report_name', '2. Ventilation des Règlements par Mode de Paiement')

@section('content')
    <table>
        <thead>
        <tr>
            <th>Date encaissement</th>
            <th>Mode de Règlement</th>
            <th class="text-right">Montant perçu (FCFA)</th>
        </tr>
        </thead>
        <tbody>
        @php $totalPayments = 0; @endphp
        @foreach($payments as $payment)
            @php $totalPayments += $payment->total; @endphp
            <tr>
                <td>{{ Carbon\Carbon::parse($payment->date)->format('d/m/Y') }}</td>
                <td class="font-bold" style="text-transform: uppercase;">{{ $payment->method }}</td>
                <td class="text-right font-bold">{{ number_format($payment->total, 0, ',', ' ') }} F</td>
            </tr>
        @endforeach
        <tr style="background-color: #f8fafc; font-size: 12px;">
            <td colspan="2" class="font-bold" style="text-transform: uppercase;">Total des flux financiers</td>
            <td class="text-right font-bold" style="color: #16a34a;">{{ number_format($totalPayments, 0, ',', ' ') }} FCFA</td>
        </tr>
        </tbody>
    </table>
@endsection
