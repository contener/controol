<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $apercu['meta']['numero'] }}</title>
    <style>
        @include('factures.pdf._styles')
        .bandeau { background: #1f2937; color: #fff; padding: 16px 20px; }
        .bandeau h1 { color: #fff; }
        .bandeau .muted { color: #d1d5db; }
        .totaux .total-final { background: #f3f4f6; }
        .paiement { margin-top: 20px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 14px; }
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
                <div class="muted">
                    @if($apercu['boutique']['adresse']) {{ $apercu['boutique']['adresse'] }}<br>@endif
                    @if($apercu['boutique']['ville']) {{ $apercu['boutique']['ville'] }}<br>@endif
                    @if($apercu['boutique']['telephone']) Tél : {{ $apercu['boutique']['telephone'] }}<br>@endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <h1>FACTURE</h1>
                <div class="muted">
                    N° {{ $apercu['meta']['numero'] }}<br>
                    Émise le {{ $apercu['meta']['date_emission'] }}<br>
                    @if($apercu['meta']['date_echeance']) Échéance : {{ $apercu['meta']['date_echeance'] }}<br>@endif
                </div>
            </td>
        </tr>
    </table>

    <div style="padding: 20px;">
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

        @include('factures.pdf._pied')
    </div>
</body>
</html>
