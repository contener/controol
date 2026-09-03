<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .hero { width: 100%; background: #6d28d9; color: #fff; border-radius: 12px; }
        .hero td { padding: 30px 32px; text-align: center; }
        .hero .logo { max-height: 50px; margin-bottom: 8px; }
        .hero h1 { font-size: 23px; margin: 0 0 6px; color: #fff; }
        .hero .sub { color: #ddd6fe; font-size: 11px; }
        .pill { display: inline-block; background: #8250e0; border-radius: 14px; padding: 5px 14px; font-size: 10px; margin: 12px 3px 0; color: #fff; }
        .cards { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .cards td { vertical-align: top; width: 50%; }
        .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; }
        .card-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; font-weight: bold; margin-bottom: 6px; }
        .muted { color: #6b7280; }
        .nui-line { color: #6b7280; font-weight: bold; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 18px; border: 1px solid #e5e7eb; border-radius: 8px; }
        table.lignes thead tr { background: #f5f3ff; }
        table.lignes th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6d28d9; padding: 10px; border-bottom: 1px solid #e5e7eb; font-weight: bold; }
        table.lignes td { padding: 10px; border-top: 1px solid #f3f4f6; }
        .text-right { text-align: right; }
        .qr-box { width: 70px; height: 70px; border: 1px dashed #d1d5db; border-radius: 8px; }
        .qr-box td { text-align: center; vertical-align: middle; font-size: 8px; color: #d1d5db; }
        .tot-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; }
        .tot-card table { width: 100%; }
        .tot-card td { padding: 3px 0; color: #6b7280; }
        .total-final { background: #6d28d9; border-radius: 8px; margin-top: 10px; }
        .total-final td { color: #fff; font-weight: bold; font-size: 14px; padding: 10px 12px; }
        .notes-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin-top: 18px; }
        .footer-note { margin-top: 18px; padding-top: 14px; border-top: 1px dashed #e5e7eb; font-size: 10px; color: #9ca3af; font-style: italic; line-height: 1.5; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <table class="hero">
        <tr>
            <td>
                @if($apercu['boutique']['logo_url'])
                    <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo"><br>
                @endif
                <h1>{{ $apercu['boutique']['nom'] }}</h1>
                @php
                    $localisation = array_filter([
                        $apercu['boutique']['adresse'] ?? null,
                        trim(($apercu['boutique']['ville'] ?? '').(($apercu['boutique']['ville'] && $apercu['boutique']['pays']) ? ', ' : '').($apercu['boutique']['pays'] ?? '')) ?: null,
                    ]);
                @endphp
                @if(count($localisation))
                    <div class="sub">&#9679; {{ implode(' — ', $localisation) }}</div>
                @endif
                <div>
                    <span class="pill">Facture {{ $apercu['meta']['numero'] }}</span>
                    @if($apercu['meta']['date_emission'])<span class="pill">Émise le {{ $apercu['meta']['date_emission'] }}</span>@endif
                    @if($apercu['meta']['date_echeance'])<span class="pill">Échéance {{ $apercu['meta']['date_echeance'] }}</span>@endif
                </div>
            </td>
        </tr>
    </table>

    <table class="cards">
        <tr>
            <td style="padding-right: 8px;">
                <div class="card">
                    <div class="card-label">Facturé à</div>
                    <strong>{{ $apercu['client']['nom'] }}</strong><br>
                    <span class="muted">
                        @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                        @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                        @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
                        @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}@endif
                    </span>
                </div>
            </td>
            <td style="padding-left: 8px;">
                <div class="card">
                    <div class="card-label">Coordonnées</div>
                    <span class="muted">
                        @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                        @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}<br>@endif
                        @if($apercu['boutique']['nui'])<span class="nui-line">NUI : {{ $apercu['boutique']['nui'] }}</span>@endif
                        @if(!$apercu['boutique']['telephone'] && !$apercu['boutique']['email'] && !$apercu['boutique']['nui']) — @endif
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <table class="lignes">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="text-right">Qté</th>
                <th class="text-right">P.U. HT</th>
                <th class="text-right">TVA %</th>
                <th class="text-right">Total TTC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($apercu['lignes'] as $ligne)
                <tr>
                    <td>
                        {{ $ligne['designation'] }}
                        @if($ligne['description'])
                            <br><span class="muted">{{ $ligne['description'] }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($ligne['quantite'], 2, ',', ' '), '0'), ',') }}</td>
                    <td class="text-right">{{ number_format($ligne['prix_unitaire'], 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($ligne['tva_taux'], 2, ',', ' ') }}</td>
                    <td class="text-right">{{ number_format($ligne['montant_ttc'], 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 16px;">
        <tr>
            <td style="width: 70px; vertical-align: top;">
                <table class="qr-box"><tr><td>QR Code</td></tr></table>
            </td>
            <td></td>
            <td style="width: 260px; vertical-align: top;">
                <div class="tot-card">
                    <table>
                        <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                        <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                        <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    </table>
                    <table class="total-final">
                        <tr><td>Total TTC</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    @if($apercu['notes'])
        <div class="notes-card">
            <div class="card-label">Notes</div>
            <p style="margin: 0;">{{ $apercu['notes'] }}</p>
        </div>
    @endif

    @if($apercu['garantie'])
        <div class="footer-note"><strong>Garantie :</strong> {{ $apercu['garantie'] }}</div>
    @endif
    @if($apercu['boutique']['note_pied_facture'])
        <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
    @endif

    <div class="footer">Merci de votre confiance.</div>
</body>
</html>
