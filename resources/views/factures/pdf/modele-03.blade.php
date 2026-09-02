<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .logo-lg { max-height: 80px; margin-bottom: 10px; }
        h1.nom { font-size: 22px; margin: 0 0 6px; font-weight: bold; }
        .muted { color: #6b7280; }
        .badge-facture { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 12px 16px; text-align: right; }
        .badge-facture .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #047857; font-weight: bold; }
        .badge-facture .numero { font-size: 16px; font-weight: bold; color: #064e3b; margin-top: 2px; }
        .client-box { border-left: 4px solid #10b981; background: #f9fafb; padding: 12px 16px; margin-top: 20px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.lignes thead tr { background: #047857; color: #fff; }
        table.lignes th { text-align: left; font-size: 10px; text-transform: uppercase; padding: 8px; }
        table.lignes td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .row-alt { background: #ecfdf5; }
        .totaux { width: 260px; margin-left: auto; margin-top: 10px; border-collapse: collapse; }
        .totaux td { padding: 3px 8px; }
        .total-chip { background: #059669; margin-top: 8px; }
        .total-chip td { padding: 10px 14px; font-weight: bold; font-size: 14px; color: #fff; }
        .paiement { margin-top: 24px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; }
        .footer { margin-top: 30px; padding-top: 16px; border-top: 1px solid #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <table style="width: 100%;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if($apercu['boutique']['logo_url'])
                    <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo-lg"><br>
                @endif
                <h1 class="nom">{{ $apercu['boutique']['nom'] }}</h1>
                <div class="muted">
                    @if($apercu['boutique']['adresse']) {{ $apercu['boutique']['adresse'] }}<br>@endif
                    @if($apercu['boutique']['ville']) {{ $apercu['boutique']['ville'] }}@if($apercu['boutique']['pays']), {{ $apercu['boutique']['pays'] }}@endif<br>@endif
                    @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                    @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
                </div>
            </td>
            <td style="width: 40%; vertical-align: top;">
                <div class="badge-facture">
                    <div class="label">Facture</div>
                    <div class="numero">{{ $apercu['meta']['numero'] }}</div>
                    <div class="muted" style="margin-top: 8px; font-size: 11px;">
                        Émission : {{ $apercu['meta']['date_emission'] ?? '—' }}<br>
                        @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}@endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div class="client-box">
        <strong>Facturé à</strong><br>
        {{ $apercu['client']['nom'] }}<br>
        @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
        @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
        @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
        @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}@endif
    </div>

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
            @foreach($apercu['lignes'] as $i => $ligne)
                <tr @if($i % 2 === 1) class="row-alt" @endif>
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

    <table class="totaux">
        <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
    </table>
    <table class="totaux total-chip">
        <tr><td>Total TTC</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
    </table>

    <div class="paiement">
        <strong>Modalités de paiement</strong><br>
        <span class="muted">
            @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
            @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
        </span>
    </div>

    @if($apercu['notes'])
        <div style="margin-top: 20px;">
            <strong>Notes</strong>
            <p>{{ $apercu['notes'] }}</p>
        </div>
    @endif

    <div class="footer">Merci de votre confiance.</div>
</body>
</html>
