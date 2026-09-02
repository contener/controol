<table class="header">
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
                @if($apercu['boutique']['email']) {{ $apercu['boutique']['email'] }}@endif
            </div>
        </td>
        <td style="width: 45%; text-align: right;">
            <h1>FACTURE {{ $apercu['meta']['numero'] }}</h1>
            <div class="muted">
                Date d'émission : {{ $apercu['meta']['date_emission'] }}<br>
                @if($apercu['meta']['date_echeance']) Date d'échéance : {{ $apercu['meta']['date_echeance'] }}<br>@endif
            </div>
        </td>
    </tr>
</table>
