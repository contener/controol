<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .topbar { width: 100%; background: #1e293b; color: #fff; }
        .topbar td { padding: 16px 20px; vertical-align: middle; }
        .topbar h1 { font-size: 16px; margin: 0; color: #fff; }
        .logo { max-height: 34px; margin-right: 8px; }
        .ref-box { border: 1px solid #64748b; border-radius: 4px; padding: 8px 14px; text-align: right; }
        .ref-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #cbd5e1; }
        .ref-numero { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .page { padding: 20px; }
        .meta-row { text-align: right; font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .meta-row strong { color: #374151; }
        .boxes { width: 100%; border-collapse: collapse; }
        .boxes td { width: 50%; vertical-align: top; }
        .party-box { border: 1px solid #cbd5e1; border-radius: 4px; padding: 12px 14px; }
        .party-label { font-size: 10px; text-transform: uppercase; color: #6b7280; font-weight: bold; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 6px; }
        .muted { color: #6b7280; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        table.lignes th { text-align: left; text-transform: uppercase; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 6px; }
        table.lignes td { padding: 6px; border: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .totaux { width: 260px; margin-left: auto; margin-top: 14px; }
        .totaux td { padding: 3px 8px; }
        .totaux .total-final { font-weight: bold; font-size: 14px; border-top: 2px solid #1e293b; }
        .conditions { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 11px; color: #6b7280; }
        .conditions-label { font-size: 10px; text-transform: uppercase; color: #475569; font-weight: bold; margin-bottom: 4px; }
        .paiement { margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; }
        .footer { margin-top: 30px; padding-top: 14px; border-top: 1px solid #e5e7eb; }
        .footer td { font-size: 10px; color: #9ca3af; vertical-align: bottom; }
        .sig-line { width: 160px; border-bottom: 1px solid #cbd5e1; height: 24px; }
    </style>
</head>
<body>
    <table class="topbar">
        <tr>
            <td style="width: 60%;">
                @if($apercu['boutique']['logo_url'])
                    <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo">
                @endif
                <h1>{{ $apercu['boutique']['nom'] }}</h1>
            </td>
            <td style="width: 40%;">
                <div class="ref-box">
                    <div class="ref-label">Référence</div>
                    <div class="ref-numero">FACTURE N° {{ $apercu['meta']['numero'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="page">
        <div class="meta-row">
            Date d'émission : <strong>{{ $apercu['meta']['date_emission'] ?? '—' }}</strong>
            @if($apercu['meta']['date_echeance'])
                &nbsp;&nbsp;&nbsp; Date d'échéance : <strong>{{ $apercu['meta']['date_echeance'] }}</strong>
            @endif
        </div>

        <table class="boxes">
            <tr>
                <td style="padding-right: 8px;">
                    <div class="party-box">
                        <div class="party-label">Émetteur</div>
                        <strong>{{ $apercu['boutique']['nom'] }}</strong><br>
                        <span class="muted">
                            @if($apercu['boutique']['adresse']) {{ $apercu['boutique']['adresse'] }}<br>@endif
                            @if($apercu['boutique']['ville']) {{ $apercu['boutique']['ville'] }} {{ $apercu['boutique']['pays'] }}<br>@endif
                            @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                            @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
                        </span>
                    </div>
                </td>
                <td style="padding-left: 8px;">
                    <div class="party-box">
                        <div class="party-label">Client</div>
                        <strong>{{ $apercu['client']['nom'] }}</strong><br>
                        <span class="muted">
                            @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                            @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                            @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
                            @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}<br>@endif
                            @if($apercu['client']['numero_fiscal']) N° fiscal : {{ $apercu['client']['numero_fiscal'] }}@endif
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
                    <th class="text-right">Remise</th>
                    <th class="text-right">TVA %</th>
                    <th class="text-right">Montant HT</th>
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
                        <td class="text-right">{{ number_format($ligne['remise_ligne'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['tva_taux'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['montant_ht'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['montant_ttc'], 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totaux">
            <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr class="total-final"><td>Total TTC</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        </table>

        <div class="conditions">
            <div class="conditions-label">Conditions générales</div>
            Facture payable sous 30 jours. Tout retard de paiement pourra entraîner des pénalités.
        </div>

        <div class="paiement">
            <strong>Coordonnées de paiement</strong><br>
            <span class="muted">
                @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
            </span>
        </div>

        @if($apercu['notes'])
            <div style="margin-top: 16px;">
                <strong>Notes</strong>
                <p>{{ $apercu['notes'] }}</p>
            </div>
        @endif

        <table class="footer" style="width: 100%;">
            <tr>
                <td style="width: 50%;">Document généré électroniquement.</td>
                <td style="width: 50%; text-align: right;">
                    <div class="sig-line" style="margin-left: auto;">&nbsp;</div>
                    Cachet et signature
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
