@extends('pdf.accounting.layout')

@section('title', 'Ventes par Catégorie')
@section('report_name', '4. Ventilation du Chiffre d\'Affaires par pôle sectoriel')

@section('content')
    <p style="margin-bottom: 15px; font-weight: bold; color: #64748b;">
        Ce rapport permet d'affecter les revenus dans vos comptes de classe 7 (OHADA) sectoriels correspondants (Bar, Cuisine, etc.).
    </p>

    <table>
        <thead>
        <tr>
            <th>Pôle / Catégorie de service</th>
            <th class="text-center">Volume d'articles vendus</th>
            <th class="text-right">Part du Chiffre d'affaires (FCFA)</th>
        </tr>
        </thead>
        <tbody>
        @php $totalRevenue = 0; @endphp
        @foreach($categories as $category)
            @php $totalRevenue += $category->total_revenue; @endphp
            <tr>
                <td class="font-bold" style="text-transform: uppercase; font-size: 10px;">{{ $category->category_name }}</td>
                <td class="text-center">{{ $category->total_qty }}</td>
                <td class="text-right font-bold">{{ number_format($category->total_revenue, 0, ',', ' ') }} F</td>
            </tr>
        @endforeach
        <tr style="background-color: #f8fafc; font-size: 11px;">
            <td colspan="2" class="font-bold" style="text-transform: uppercase;">Validation de l'assiette globale</td>
            <td class="text-right font-bold" style="color: #2563eb;">{{ number_format($totalRevenue, 0, ',', ' ') }} FCFA</td>
        </tr>
        </tbody>
    </table>
@endsection
