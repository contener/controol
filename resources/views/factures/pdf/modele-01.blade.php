<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        @include('factures.pdf._styles')
        .accent-bar { background: #1d4ed8; height: 6px; width: 100%; }
        .page { padding: 24px 28px 6px; }
        .header-std { width: 100%; border-bottom: 2px solid #f3f4f6; padding-bottom: 18px; margin-bottom: 4px; }
        .header-std td { vertical-align: top; }
        .header-std h1 { font-size: 22px; margin: 0 0 8px; }
        .label-accent { font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; color: #1d4ed8; font-weight: bold; }
        .numero-lg { font-size: 22px; font-weight: bold; margin-top: 2px; }
        .loc-block { margin-top: 10px; font-size: 11px; }
        .loc-block .pin { color: #1d4ed8; font-weight: bold; margin-right: 3px; }
        .contact-block { margin-top: 8px; font-size: 11px; }
        .nui-line { color: #6b7280; font-size: 11px; font-weight: bold; }
        .box { border-left: 4px solid #1d4ed8; border-radius: 0 8px 8px 0; padding: 16px 18px; margin-top: 26px; }
        table.lignes thead tr { background: #111827; color: #fff; }
        table.lignes th { padding: 10px 12px; color: #fff; }
        table.lignes td { padding: 10px 12px; }
        table.lignes tbody tr:nth-child(even) { background: #f9fafb; }
        .totaux { margin-top: 20px; }
        .totaux .total-final { background: #1d4ed8; border-top: none; }
        .totaux .total-final td { color: #fff; font-size: 15px; padding: 12px 10px; }
        .footer-note { margin-top: 26px; padding-top: 14px; border-top: 1px dashed #e5e7eb; font-size: 10px; color: #9ca3af; font-style: italic; line-height: 1.5; }
        .signature-row { width: 100%; margin-top: 22px; padding-top: 18px; border-top: 1px solid #e5e7eb; }
        .signature-row td { vertical-align: bottom; font-size: 10px; color: #9ca3af; }
        .sig-line-std { border-bottom: 1px solid #d1d5db; width: 140px; height: 28px; margin: 0 auto 4px; }
    </style>
</head>
<body>
    <div class="accent-bar"></div>
    <div class="page">
        <table class="header-std">
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
                    <div class="contact-block muted">
                        @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                        @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}<br>@endif
                        @if($apercu['boutique']['nui'])<span class="nui-line">NUI : {{ $apercu['boutique']['nui'] }}</span>@endif
                    </div>
                </td>
                <td style="width: 45%; text-align: right;">
                    <div class="label-accent">Facture</div>
                    <div class="numero-lg">{{ $apercu['meta']['numero'] }}</div>
                    <div class="muted" style="margin-top: 8px; font-size: 11px;">
                        Émission : {{ $apercu['meta']['date_emission'] ?? '—' }}<br>
                        @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}@endif
                    </div>
                </td>
            </tr>
        </table>

        @include('factures.pdf._client')
        @include('factures.pdf._lignes')
        @include('factures.pdf._totaux')

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
