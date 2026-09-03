<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; font-weight: 300; }
        .wrap { padding: 40px 60px; border: 1px solid #f3f4f6; }
        .center { text-align: center; }
        h1 { font-size: 24px; letter-spacing: 5px; text-transform: uppercase; margin: 0 0 8px; font-weight: 400; }
        .logo { max-height: 45px; margin-bottom: 14px; }
        .muted { color: #9ca3af; }
        .small { font-size: 10px; }
        .tracked { letter-spacing: 2px; text-transform: uppercase; }
        .rule { width: 40px; height: 1px; background: #d1d5db; margin: 30px auto; }
        .locline { color: #9ca3af; font-size: 10px; letter-spacing: 0.5px; margin-top: 8px; }
        table.infos { width: 100%; font-size: 11px; margin-top: 10px; }
        table.infos td { vertical-align: top; width: 50%; }
        .lbl { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .nom { font-size: 13px; }
        table.lignes { width: 100%; border-collapse: collapse; margin-top: 40px; }
        table.lignes th { text-align: left; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #9ca3af; font-weight: 400; border-bottom: 1px solid #d1d5db; padding: 8px 4px; }
        table.lignes td { padding: 14px 4px; border-bottom: 1px solid #f3f4f6; }
        .text-right { text-align: right; }
        .total-block { text-align: center; margin-top: 50px; padding-top: 40px; border-top: 1px solid #f3f4f6; }
        .total-block .lbl { margin-bottom: 8px; }
        .total-block .amount { font-size: 36px; }
        .total-block .rule2 { width: 40px; height: 1px; background: #d1d5db; margin: 16px auto 12px; }
        .total-block .detail { color: #9ca3af; font-size: 10px; }
        .detail span { margin: 0 10px; }
        .footer-note { margin-top: 40px; padding-top: 24px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 10px; color: #9ca3af; font-style: italic; letter-spacing: 0.3px; }
        .footer { margin-top: 50px; width: 100%; font-size: 10px; color: #9ca3af; }
        .sig-line { width: 140px; border-bottom: 1px solid #e5e7eb; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="center">
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
            <div class="muted small" style="margin-top: 4px;">
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }} @endif
                @if($apercu['boutique']['nui']) &middot; NUI {{ $apercu['boutique']['nui'] }} @endif
            </div>
        </div>

        <div class="rule"></div>

        <table class="infos">
            <tr>
                <td>
                    <div class="lbl">Facturé à</div>
                    <div class="nom">{{ $apercu['client']['nom'] }}</div>
                    <div class="muted" style="margin-top: 4px;">
                        @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
                        @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
                        @if($apercu['client']['email']) {{ $apercu['client']['email'] }}@endif
                    </div>
                </td>
                <td class="text-right">
                    <div class="lbl">Facture</div>
                    <div class="nom">{{ $apercu['meta']['numero'] }}</div>
                    <div class="muted" style="margin-top: 4px;">
                        {{ $apercu['meta']['date_emission'] }}<br>
                        @if($apercu['meta']['date_echeance']) Échéance {{ $apercu['meta']['date_echeance'] }}@endif
                    </div>
                </td>
            </tr>
        </table>

        <table class="lignes">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="text-right">Qté</th>
                    <th class="text-right">Prix</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($apercu['lignes'] as $ligne)
                    <tr>
                        <td>
                            {{ $ligne['designation'] }}
                            @if($ligne['description'])
                                <br><span class="muted small">{{ $ligne['description'] }}</span>
                            @endif
                        </td>
                        <td class="text-right muted">{{ rtrim(rtrim(number_format($ligne['quantite'], 2, ',', ' '), '0'), ',') }}</td>
                        <td class="text-right muted">{{ number_format($ligne['prix_unitaire'], 2, ',', ' ') }}</td>
                        <td class="text-right">{{ number_format($ligne['montant_ttc'], 2, ',', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total-block">
            <div class="lbl">Total à payer</div>
            <div class="amount">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</div>
            <div class="rule2"></div>
            <div class="detail">
                <span>Sous-total {{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }}</span>
                <span>TVA {{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }}</span>
                @if($apercu['totaux']['remise'])
                    <span>Remise - {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }}</span>
                @endif
            </div>
        </div>

        @if($apercu['notes'])
            <div class="center muted small" style="margin-top: 40px;">
                <p>{{ $apercu['notes'] }}</p>
            </div>
        @endif

        @if($apercu['garantie'])
            <div class="footer-note"><strong>Garantie :</strong> {{ $apercu['garantie'] }}</div>
        @endif
        @if($apercu['boutique']['note_pied_facture'])
            <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
        @endif

        <table class="footer">
            <tr>
                <td class="tracked">Merci de votre confiance.</td>
                <td class="text-right">
                    <div class="sig-line" style="margin-left: auto;">&nbsp;</div>
                    <span class="tracked">Signature</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
