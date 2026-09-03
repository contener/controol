<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .header { width: 100%; margin-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { max-height: 60px; margin-bottom: 6px; border-radius: 8px; }
        h1 { font-size: 22px; margin: 0 0 6px; color: #6d28d9; letter-spacing: -0.3px; }
        .muted { color: #6b7280; }
        .locline { color: #4b5563; font-weight: bold; margin: 4px 0; }
        .statut { display: inline-block; padding: 3px 12px; border-radius: 10px; font-size: 10px; font-weight: bold; }
        .statut-brouillon { background: #f3f4f6; color: #374151; }
        .statut-envoyee { background: #fef9c3; color: #854d0e; }
        .statut-payee { background: #dcfce7; color: #166534; }
        .statut-annulee { background: #fee2e2; color: #991b1b; }
        .facture-box { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 10px 14px; margin-top: 6px; }
        .facture-box .lbl { font-size: 9px; text-transform: uppercase; color: #7c3aed; font-weight: bold; }
        .facture-box .num { font-weight: bold; color: #5b21b6; font-size: 15px; }
        .infos { width: 100%; margin-top: 4px; margin-bottom: 6px; }
        .infos td { vertical-align: top; width: 50%; }
        .box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; }
        .box .lbl { font-size: 9px; text-transform: uppercase; color: #6d28d9; font-weight: bold; margin-bottom: 4px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.lignes th { text-align: left; font-size: 10px; text-transform: uppercase; color: #fff; background: #7c3aed; padding: 8px 8px; }
        table.lignes td { padding: 8px 8px; border-bottom: 1px solid #f3f4f6; }
        table.lignes tr.alt td { background: #faf9ff; }
        .text-right { text-align: right; }
        .remise-col { color: #dc2626; }
        .totaux { width: 270px; margin-left: auto; margin-top: 14px; }
        .totaux td { padding: 4px 8px; }
        .totaux .total-final { font-weight: bold; font-size: 15px; background: #7c3aed; color: #fff; border-radius: 5px; }
        .totaux .total-final td { padding: 10px 10px; }
        .bottom-row { width: 100%; margin-top: 14px; }
        .bottom-row td { vertical-align: top; }
        .qr-box { border: 1px dashed #d1d5db; border-radius: 6px; width: 120px; text-align: center; padding: 10px; font-size: 9px; color: #9ca3af; }
        .qr-square { width: 50px; height: 50px; border: 1px solid #e5e7eb; margin: 0 auto 6px; line-height: 50px; text-align: center; color: #d1d5db; font-size: 9px; }
        .footer-note { margin-top: 20px; padding-top: 10px; border-top: 1px dashed #e5e7eb; text-align: center; font-size: 10px; color: #9ca3af; font-style: italic; }
        .footer { margin-top: 18px; text-align: center; font-size: 10px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>
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
                    @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}<br>@endif
                    @if($apercu['boutique']['nui']) NUI : {{ $apercu['boutique']['nui'] }}@endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <span class="statut statut-{{ $statut }}">{{ $statutLabel }}</span>
                <div class="facture-box">
                    <div class="lbl">Commande / Facture</div>
                    <div class="num">{{ $apercu['meta']['numero'] }}</div>
                    <div class="muted">
                        Émise : {{ $apercu['meta']['date_emission'] }}<br>
                        @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}@endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="infos">
        <tr>
            <td style="padding-right: 8px;">
                <div class="box">
                    <div class="lbl">Facturé à</div>
                    <strong>{{ $apercu['client']['nom'] }}</strong><br>
                    @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                    @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                    @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
                    @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}@endif
                </div>
            </td>
            <td style="padding-left: 8px;">
                <div class="box">
                    <div class="lbl">Livraison</div>
                    <strong>{{ $apercu['client']['nom'] }}</strong><br>
                    @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                    @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}@endif
                    @if(!$apercu['client']['adresse'] && !$apercu['client']['ville'])
                        <span class="muted">Adresse de livraison non renseignée.</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="lignes">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="text-right">Qté</th>
                <th class="text-right">Prix unitaire</th>
                <th class="text-right">Remise</th>
                <th class="text-right">Total</th>
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
                    <td class="text-right remise-col">{{ $ligne['remise_ligne'] ? '- '.number_format($ligne['remise_ligne'], 2, ',', ' ') : '—' }}</td>
                    <td class="text-right">{{ number_format($ligne['montant_ttc'], 2, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="bottom-row">
        <tr>
            <td style="width: 130px;">
                <div class="qr-box">
                    <div class="qr-square">QR</div>
                    Lien de commande
                </div>
            </td>
            <td>
                <table class="totaux">
                    <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                    <tr class="total-final"><td>Total</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($apercu['notes'])
        <div style="margin-top: 20px;">
            <strong>Notes</strong>
            <p>{{ $apercu['notes'] }}</p>
        </div>
    @endif

    @if($apercu['garantie'])
        <div class="footer-note"><strong>Garantie :</strong> {{ $apercu['garantie'] }}</div>
    @endif
    @if($apercu['boutique']['note_pied_facture'])
        <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
    @endif

    <div class="footer">Merci pour votre commande !</div>
</body>
</html>
