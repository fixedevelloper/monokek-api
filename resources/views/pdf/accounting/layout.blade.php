<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>@yield('title')</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 5px;
            color: #2563eb;
        }
        .period {
            font-style: italic;
            color: #64748b;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 8px 10px;
            text-align: left;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

<div class="header">
    @php
        // On utilise ton helper statique get() pour cibler précisément la bonne clé
        $companyName = \App\Models\Setting::get('name', 'Monekek');
        $companyPhone = \App\Models\Setting::get('phone', '');
        $companyAddress = \App\Models\Setting::get('address', '');
    @endphp

    <table style="border: none; margin: 0; width: 100%;">
        <tr>
            <td style="border: none; padding: 0; vertical-align: top;">
                <div class="company-name">{{ $companyName }}</div>
                <div class="report-title">@yield('report_name')</div>
                <div class="period">Période du {{ $start->format('d/m/Y') }} au {{ $end->format('d/m/Y') }}</div>
                @if(!empty($companyAddress))
                    <div style="font-size: 9px; color: #64748b; margin-top: 2px;">{{ $companyAddress }}</div>
                @endif
            </td>
            <td style="border: none; padding: 0; text-align: right; vertical-align: top; color: #64748b;">
                Généré le {{ now()->format('d/m/Y H:i') }}<br>
                Format: PDF Officiel
                @if(!empty($companyPhone))
                    <br><span style="font-size: 9px;">Tél: {{ $companyPhone }}</span>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="content">
    @yield('content')
</div>

<div class="footer">
    Système de gestion {{$companyName}} - Document confidentiel destiné aux services comptables.
</div>

</body>
</html>
