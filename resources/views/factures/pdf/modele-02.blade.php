<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        @include('factures.pdf._styles')
        .bandeau { background: #111827; color: #fff; padding: 22px 26px; border-radius: 0 0 10px 10px; }
        .bandeau h1 { color: #fff; font-size: 21px; }
        .bandeau .muted { color: #d1d5db; }
        .bandeau .loc-block { margin-top: 10px; font-size: 11px; color: #e5e7eb; }
        .bandeau .loc-block .pin { color: #9ca3af; margin-right: 3px; }
        .bandeau .contact-block { margin-top: 8px; font-size: 11px; color: #9ca3af; }
        .bandeau .nui-line { color: #9ca3af; font-weight: bold; }
        .bandeau .facture-title { font-size: 20px; letter-spacing: 3px; }
        .bandeau .rule { width: 60px; height: 2px; background: rgba(255,255,255,0.25); margin: 8px 0 0 auto; }
        .bandeau .numero-lg { font-size: 15px; font-weight: bold; color: #fff; margin-top: 10px; }
        .page-body { padding: 26px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 18px; }
        table.lignes thead tr { background: #111827; }
        table.lignes th { padding: 9px 10px; color: #fff; }
        table.lignes td { padding: 9px 10px; }
        table.lignes tbody tr:nth-child(even) { background: #f9fafb; }
        .totaux { margin-top: 20px; }
        .totaux .total-final { background: #111827; border-top: none; }
        .totaux .total-final td { color: #fff; font-size: 15px; padding: 12px 10px; }
        .paiement { margin-top: 26px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px 16px; }
        .footer-note { margin-top: 26px; padding-top: 14px; border-top: 1px dashed #e5e7eb; font-size: 10px; color: #9ca3af; font-style: italic; line-height: 1.5; }
        .signature-row { width: 100%; margin-top: 22px; padding-top: 18px; border-top: 1px solid #e5e7eb; }
        .signature-row td { vertical-align: bottom; font-size: 10px; color: #9ca3af; }
        .sig-line-std { border-bottom: 1px solid #d1d5db; width: 140px; height: 28px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <table class="header bandeau">
        <tr>
            <td style="width: 55%;">
                @if($apercu['boutique']['logo_url'])
                    <img src="{{ $apercu['boutique']['logo_url'] }}" class="logo"><br>
                @endif
                <h1>{{ $apercu['boutique']['nom'] }}</h1>
                @if($apercu['boutique']['adresse'] || $apercu['boutique']['ville'] || $apercu['boutique']['pays'])
                    <div class="loc-block">
                        <span class="pin">&#9679;</span>
                        @if($apercu['boutique']['adresse']) {{ $apercu['boutique']['adresse'] }}<br>@endif
                        @if($apercu['boutique']['ville'] || $apercu['boutique']['pays'])
                            {{ trim(($apercu['boutique']['ville'] ?? '').(($apercu['boutique']['ville'] && $apercu['boutique']['pays']) ? ', ' : '').($apercu['boutique']['pays'] ?? '')) }}
                        @endif
                    </div>
                @endif
                <div class="contact-block">
                    @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                    @if($apercu['boutique']['nui'])<span class="nui-line">NUI : {{ $apercu['boutique']['nui'] }}</span>@endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <h1 class="facture-title">FACTURE</h1>
                <div class="rule"></div>
                <div class="numero-lg">N° {{ $apercu['meta']['numero'] }}</div>
                <div class="muted" style="margin-top: 6px; font-size: 11px;">
                    Émise le {{ $apercu['meta']['date_emission'] }}<br>
                    @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}<br>@endif
                </div>
            </td>
        </tr>
    </table>

    <div class="page-body">
        @include('factures.pdf._client')
        @include('factures.pdf._lignes')
        @include('factures.pdf._totaux')

        <div class="paiement">
            <strong>Coordonnées de paiement</strong><br>
            <span class="muted">
                @if($apercu['boutique']['whatsapp']) WhatsApp : {{ $apercu['boutique']['whatsapp'] }}<br>@endif
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
            </span>
        </div>

        @if($apercu['notes'])
            <div style="margin-top: 24px;">
                <strong>Notes</strong>
                <p>{{ $apercu['notes'] }}</p>
            </div>
        @endif

        @if($apercu['boutique']['note_pied_facture'])
            <div class="footer-note">{{ $apercu['boutique']['note_pied_facture'] }}</div>
        @endif

        <table class="signature-row">
            <tr>
                <td style="width: 60%;">Merci de votre confiance.</td>
                <td style="width: 40%; text-align: center;">
                    <div class="sig-line-std">&nbsp;</div>
                    Signature
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
