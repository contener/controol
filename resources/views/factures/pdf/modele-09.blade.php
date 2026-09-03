<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        .wrap { padding: 20px 28px 26px; border-top: 4px solid #1e3a8a; }
        .header { width: 100%; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
        .header td { vertical-align: top; }
        .logo { max-height: 50px; margin-bottom: 4px; }
        h1 { font-size: 18px; margin: 0 0 5px; color: #1e3a8a; letter-spacing: -0.2px; }
        h2 { font-size: 15px; margin: 0; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1.5px; }
        .muted { color: #6b7280; }
        .locline { color: #4b5563; font-weight: bold; margin: 4px 0; font-size: 10px; }
        .statut { display: inline-block; padding: 2px 9px; border-radius: 4px; font-size: 9px; font-weight: bold; margin-top: 3px; }
        .statut-brouillon { background: #f3f4f6; color: #374151; }
        .statut-envoyee { background: #fef9c3; color: #854d0e; }
        .statut-payee { background: #dcfce7; color: #166534; }
        .statut-annulee { background: #fee2e2; color: #991b1b; }
        table.meta { margin-left: auto; margin-top: 6px; font-size: 10px; }
        table.meta td { padding: 1px 0; }
        table.meta .k { color: #9ca3af; text-align: right; padding-right: 8px; }
        table.meta .v { font-weight: bold; text-align: right; }
        table.infos { width: 100%; margin-top: 10px; border-collapse: collapse; }
        table.infos td { vertical-align: top; width: 50%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .lbl { font-size: 9px; text-transform: uppercase; color: #1e3a8a; font-weight: bold; margin-bottom: 4px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        table.lignes th { text-align: left; text-transform: uppercase; color: #fff; background: #1e3a8a; padding: 7px 5px; font-size: 9px; }
        table.lignes td { padding: 6px 5px; border-bottom: 1px solid #f3f4f6; }
        table.lignes tr.alt td { background: #f8fafc; }
        .text-right { text-align: right; }
        .bottom { width: 100%; margin-top: 10px; }
        .bottom td { vertical-align: top; }
        .conditions { color: #9ca3af; font-size: 9px; width: 280px; }
        .totaux { width: 250px; margin-left: auto; }
        .totaux td { padding: 2px 6px; font-size: 10px; }
        .totaux .total-final { font-weight: bold; font-size: 12px; background: #1e3a8a; color: #fff; border-radius: 4px; }
        .totaux .total-final td { padding: 7px 8px; }
        .paiement { margin-top: 10px; border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; font-size: 10px; }
        .footer-note { margin-top: 10px; font-size: 9px; color: #9ca3af; font-style: italic; }
        .footer { margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 10px; }
        .sig-line { width: 150px; border-bottom: 1px solid #d1d5db; margin-bottom: 3px; }
    </style>
</head>
<body>
    <div class="wrap">
        @php
            $statutLabels = ['brouillon' => 'Brouillon', 'envoyee' => 'Envoyée', 'payee' => 'Payée', 'annulee' => 'Annulée'];
            $statut = $apercu['meta']['statut'] ?? 'brouillon';
            $statutLabel = $statutLabels[$statut] ?? $statut;
        @endphp

        <table class="header">
            <tr>
                <td style="width: 55%;">
                    @if($apercu['boutique']['logo_url'])
                        <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo"><br>
                    @endif
                    <h1>{{ $apercu['boutique']['nom'] }}</h1>
                    @if($apercu['boutique']['adresse'] || $apercu['boutique']['ville'])
                        <div class="locline">
                            &#128205;
                            @if($apercu['boutique']['adresse']){{ $apercu['boutique']['adresse'] }}@if($apercu['boutique']['ville']), @endif @endif
                            @if($apercu['boutique']['ville']){{ $apercu['boutique']['ville'] }}@endif
                            @if($apercu['boutique']['pays']), {{ $apercu['boutique']['pays'] }}@endif
                        </div>
                    @endif
                    <div class="muted">
                        @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                        @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}<br>@endif
                        @if($apercu['boutique']['nui']) NUI : {{ $apercu['boutique']['nui'] }}@endif
                    </div>
                </td>
                <td style="width: 45%; text-align: right;">
                    <h2>Facture</h2>
                    <span class="statut statut-{{ $statut }}">{{ $statutLabel }}</span>
                    <table class="meta">
                        <tr><td class="k">N° facture</td><td class="v">{{ $apercu['meta']['numero'] }}</td></tr>
                        @if($apercu['client']['numero_fiscal'])
                            <tr><td class="k">Référence client</td><td class="v">{{ $apercu['client']['numero_fiscal'] }}</td></tr>
                        @endif
                        <tr><td class="k">Émission</td><td class="v">{{ $apercu['meta']['date_emission'] }}</td></tr>
                        @if($apercu['meta']['date_echeance'])
                            <tr><td class="k">Échéance</td><td class="v">{{ $apercu['meta']['date_echeance'] }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="infos">
            <tr>
                <td>
                    <div class="lbl">Émetteur</div>
                    <strong>{{ $apercu['boutique']['nom'] }}</strong><br>
                    <span class="muted">
                        @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
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
                        @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}@endif
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
                    <th class="text-right">Remise</th>
                    <th class="text-right">TVA %</th>
                    <th class="text-right">Mont. HT</th>
                    <th class="text-right">Mont. TTC</th>
                </tr>
            </thead>
            <tbody>
                @foreach($apercu['lignes'] as $ligne)
                    <tr @if($loop->index % 2 === 1) class="alt" @endif>
                        <td>
                            {{ $ligne['designation'] }}
                            @if($ligne['description'])
                                <br><span class="muted">{{ $ligne['description'] }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ rtrim(rtrim(number_format($ligne['quantite'], 2, ',', ' '), '0'), ',') }}</td>
                        <td class="text-right">{{ number_format($ligne['prix_unitaire'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ $ligne['remise_ligne'] ? number_format($ligne['remise_ligne'], 2, ',', ' ') : '—' }}</td>
                        <td class="text-right">{{ number_format($ligne['tva_taux'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['montant_ht'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['montant_ttc'], 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="bottom">
            <tr>
                <td>
                    <div class="conditions">
                        <strong class="muted">Conditions</strong><br>
                        Paiement dû à la date d'échéance indiquée. Toute somme non réglée à l'échéance pourra donner lieu à des pénalités, conformément aux conditions générales de vente.
                    </div>
                </td>
                <td>
                    <table class="totaux">
                        <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                        <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                        <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                        <tr class="total-final"><td>Total TTC</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>

        @if($apercu['notes'])
            <div style="margin-top: 10px;">
                <strong class="muted">Notes</strong>
                <p>{{ $apercu['notes'] }}</p>
            </div>
        @endif

        @if($apercu['boutique']['note_pied_facture'])
            <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
        @endif

        <div class="footer">
            <strong class="muted">Signature autorisée</strong><br>
            <div class="sig-line">&nbsp;</div>
            Pour {{ $apercu['boutique']['nom'] }}
        </div>
    </div>
</body>
</html>
