<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: "DejaVu Serif", serif; font-size: 12px; color: #1f2937; }
        .page { padding: 10px 20px; }
        .header { width: 100%; border-bottom: 2px solid #b45309; padding-bottom: 18px; }
        .header td { vertical-align: top; }
        .logo { max-height: 46px; margin-bottom: 8px; }
        h1.nom { font-size: 20px; margin: 0 0 8px; font-weight: bold; letter-spacing: 0.5px; }
        .muted { color: #6b7280; font-size: 11px; }
        .loc-block { font-size: 11px; color: #57534e; margin-bottom: 4px; }
        .loc-block .pin { color: #b45309; margin-right: 3px; }
        .nui-line { color: #6b7280; font-size: 11px; font-weight: bold; }
        .label-gold { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #b45309; font-weight: bold; }
        .numero { font-size: 17px; font-weight: bold; margin-top: 4px; }
        .client-section { margin-top: 30px; }
        .client-nom { font-size: 13px; font-weight: bold; margin-top: 6px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 30px; }
        table.lignes th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; border-bottom: 2px solid #b45309; padding: 8px 4px 10px; font-weight: bold; }
        table.lignes td { padding: 11px 4px; border-bottom: 1px solid #f0f0f0; }
        .text-right { text-align: right; }
        .totaux { width: 280px; margin-left: auto; margin-top: 16px; }
        .totaux td { padding: 4px 4px; color: #6b7280; }
        .total-final { width: 280px; margin-left: auto; margin-top: 10px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; }
        .total-final td { padding: 10px 14px; font-weight: bold; font-size: 15px; }
        .total-final .label-final { font-size: 11px; letter-spacing: 0.5px; color: #78716c; font-weight: normal; }
        .total-final .montant { color: #92400e; }
        .conditions { margin-top: 30px; font-size: 11px; color: #6b7280; }
        .footer-note { margin-top: 26px; padding-top: 14px; border-top: 1px dashed #fde68a; font-size: 10px; color: #9ca3af; font-style: italic; line-height: 1.5; }
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { width: 50%; text-align: center; font-size: 11px; color: #6b7280; padding: 0 20px; }
        .sig-line { border-bottom: 1px solid #d1d5db; height: 34px; }
        .footer { margin-top: 34px; padding-top: 14px; border-top: 1px solid #b45309; text-align: center; font-size: 10px; color: #9ca3af; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="page">
        <table class="header">
            <tr>
                <td style="width: 60%;">
                    @if($apercu['boutique']['logo_url'])
                        <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo"><br>
                    @endif
                    <h1 class="nom">{{ $apercu['boutique']['nom'] }}</h1>
                    @php
                        $localisation = array_filter([
                            $apercu['boutique']['adresse'] ?? null,
                            trim(($apercu['boutique']['ville'] ?? '').(($apercu['boutique']['ville'] && $apercu['boutique']['pays']) ? ', ' : '').($apercu['boutique']['pays'] ?? '')) ?: null,
                        ]);
                    @endphp
                    @if(count($localisation))
                        <div class="loc-block"><span class="pin">&#9679;</span> {{ implode(' — ', $localisation) }}</div>
                    @endif
                    <div class="muted">
                        @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                        @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}<br>@endif
                        @if($apercu['boutique']['nui'])<span class="nui-line">NUI : {{ $apercu['boutique']['nui'] }}</span>@endif
                    </div>
                </td>
                <td style="width: 40%; text-align: right;">
                    <div class="label-gold">Facture</div>
                    <div class="numero">{{ $apercu['meta']['numero'] }}</div>
                    <div class="muted" style="margin-top: 6px;">
                        Émission : {{ $apercu['meta']['date_emission'] ?? '—' }}<br>
                        @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}@endif
                    </div>
                </td>
            </tr>
        </table>

        <div class="client-section">
            <div class="label-gold">Facturé à</div>
            <div class="client-nom">{{ $apercu['client']['nom'] }}</div>
            <div class="muted" style="margin-top: 4px;">
                @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
                @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}<br>@endif
                @if($apercu['client']['numero_fiscal']) N° fiscal : {{ $apercu['client']['numero_fiscal'] }}@endif
            </div>
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
            <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
            <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        </table>
        <table class="total-final">
            <tr><td><span class="label-final">Total TTC</span></td><td class="text-right montant">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        </table>

        <div class="conditions">
            <div class="label-gold">Conditions de paiement</div>
            <div style="margin-top: 4px;">
                @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
            </div>
        </div>

        @if($apercu['notes'])
            <div style="margin-top: 20px;">
                <div class="label-gold">Notes</div>
                <p class="muted" style="margin-top: 4px;">{{ $apercu['notes'] }}</p>
            </div>
        @endif

        @if($apercu['garantie'])
            <div class="footer-note"><strong>Garantie :</strong> {{ $apercu['garantie'] }}</div>
        @endif
        @if($apercu['boutique']['note_pied_facture'])
            <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line">&nbsp;</div>
                    <div style="margin-top: 6px;">Le fournisseur</div>
                </td>
                <td>
                    <div class="sig-line">&nbsp;</div>
                    <div style="margin-top: 6px;">Le client</div>
                </td>
            </tr>
        </table>

        <div class="footer">MERCI DE VOTRE CONFIANCE</div>
    </div>
</body>
</html>
