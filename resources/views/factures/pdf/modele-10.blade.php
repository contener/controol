<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .masthead { width: 100%; background: #0f172a; border-bottom: 4px solid #f59e0b; padding: 20px 30px; }
        .masthead td { vertical-align: middle; }
        .logo { max-height: 50px; background: #fff; padding: 4px; border-radius: 4px; }
        .masthead h1 { color: #fff; font-size: 22px; margin: 0 0 2px; letter-spacing: 1px; }
        .masthead .sub { color: #cbd5e1; font-size: 10px; }
        .masthead .facture-lbl { color: #f59e0b; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; }
        .masthead .facture-num { color: #fff; font-size: 17px; font-weight: bold; }
        .wrap { padding: 24px 30px; }
        .muted { color: #6b7280; }
        table.meta-dates { width: 100%; font-size: 11px; margin-bottom: 16px; }
        table.meta-dates .k { color: #9ca3af; padding-right: 6px; }
        table.meta-dates .v { font-weight: bold; padding-right: 30px; }
        table.infos { width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; border-radius: 4px; }
        table.infos td { vertical-align: top; width: 50%; padding: 12px 14px; }
        table.infos td.sep { border-right: 1px solid #cbd5e1; }
        .lbl { font-size: 9px; text-transform: uppercase; color: #0f172a; font-weight: bold; letter-spacing: 1px; margin-bottom: 6px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.lignes th { text-align: left; font-size: 10px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #0f172a; padding: 7px 6px; }
        table.lignes td { padding: 7px 6px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .totaux { width: 280px; margin-left: auto; margin-top: 14px; }
        .totaux td { padding: 3px 8px; }
        .totaux .total-final { font-weight: bold; font-size: 14px; background: #0f172a; color: #fff; border-radius: 4px; }
        .totaux .paye { padding-top: 8px; }
        .totaux .solde { font-weight: bold; }
        .solde-du { color: #d97706; }
        .solde-ok { color: #16a34a; }
        .paiement { margin-top: 18px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 10px 14px; }
        .conditions { margin-top: 14px; font-size: 10px; color: #9ca3af; }
        table.signatures { width: 100%; margin-top: 30px; border-collapse: collapse; }
        table.signatures td { width: 50%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 14px; vertical-align: top; font-size: 10px; color: #6b7280; }
        table.signatures .who { font-weight: bold; color: #0f172a; margin-bottom: 30px; display: block; }
        .sig-line { border-bottom: 1px solid #cbd5e1; margin-bottom: 4px; }
    </style>
</head>
<body>
    @php
        $statut = $apercu['meta']['statut'] ?? 'brouillon';
        $totalTtc = (float) $apercu['totaux']['total_ttc'];
        $montantPaye = $statut === 'payee' ? $totalTtc : 0;
        $soldeRestant = $totalTtc - $montantPaye;
    @endphp

    <table class="masthead">
        <tr>
            <td style="width: 65%;">
                @if($apercu['boutique']['logo_url'])
                    <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo"><br>
                @endif
                <h1>{{ $apercu['boutique']['nom'] }}</h1>
                <div class="sub">
                    @if($apercu['boutique']['ville']) {{ $apercu['boutique']['ville'] }}@endif
                    @if($apercu['boutique']['ville'] && $apercu['boutique']['pays']), @endif
                    @if($apercu['boutique']['pays']) {{ $apercu['boutique']['pays'] }}@endif
                </div>
            </td>
            <td style="width: 35%; text-align: right;">
                <div class="facture-lbl">Facture</div>
                <div class="facture-num">{{ $apercu['meta']['numero'] }}</div>
            </td>
        </tr>
    </table>

    <div class="wrap">
        <table class="meta-dates">
            <tr>
                <td class="k">Date d'émission</td>
                <td class="v">{{ $apercu['meta']['date_emission'] }}</td>
                <td class="k">Date d'échéance</td>
                <td class="v">{{ $apercu['meta']['date_echeance'] ?: '—' }}</td>
            </tr>
        </table>

        <table class="infos">
            <tr>
                <td class="sep">
                    <div class="lbl">Émetteur</div>
                    <strong>{{ $apercu['boutique']['nom'] }}</strong><br>
                    <span class="muted">
                        @if($apercu['boutique']['adresse']) {{ $apercu['boutique']['adresse'] }}<br>@endif
                        @if($apercu['boutique']['ville']) {{ $apercu['boutique']['ville'] }}, {{ $apercu['boutique']['pays'] }}<br>@endif
                        @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                        @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
                    </span>
                </td>
                <td>
                    <div class="lbl">Facturé à</div>
                    <strong>{{ $apercu['client']['nom'] }}</strong><br>
                    <span class="muted">
                        @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                        @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                        @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
                        @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}<br>@endif
                        @if($apercu['client']['numero_fiscal']) Réf. : {{ $apercu['client']['numero_fiscal'] }}@endif
                    </span>
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

        <table class="totaux">
            <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr class="total-final"><td>Total TTC</td><td class="text-right">{{ number_format($totalTtc, 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr class="paye"><td>Montant payé</td><td class="text-right">{{ number_format($montantPaye, 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr class="solde {{ $soldeRestant > 0 ? 'solde-du' : 'solde-ok' }}"><td>Solde restant</td><td class="text-right">{{ number_format($soldeRestant, 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        </table>

        <div class="paiement">
            <div class="lbl">Méthode de paiement</div>
            <span class="muted">
                @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
            </span>
        </div>

        <div class="conditions">
            <strong>Conditions</strong><br>
            Facture payable selon les modalités convenues. Merci de bien vouloir mentionner le numéro de facture lors de tout règlement.
        </div>

        @if($apercu['notes'])
            <div style="margin-top: 14px;">
                <strong class="muted">Notes</strong>
                <p>{{ $apercu['notes'] }}</p>
            </div>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <span class="who">Pour {{ $apercu['boutique']['nom'] }}</span>
                    <div class="sig-line">&nbsp;</div>
                    Signature &amp; date
                </td>
                <td>
                    <span class="who">Pour {{ $apercu['client']['nom'] }}</span>
                    <div class="sig-line">&nbsp;</div>
                    Signature &amp; date
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
