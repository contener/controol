<table style="width: 100%; margin-bottom: 10px;">
    <tr>
        <td class="box" style="width: 45%;">
            <strong>Facturé à</strong><br>
            {{ $apercu['client']['nom'] }}<br>
            @if($apercu['client']['adresse']) {{ $apercu['client']['adresse'] }}<br>@endif
            @if($apercu['client']['ville']) {{ $apercu['client']['ville'] }} {{ $apercu['client']['pays'] }}<br>@endif
            @if($apercu['client']['email']) {{ $apercu['client']['email'] }}<br>@endif
            @if($apercu['client']['telephone']) {{ $apercu['client']['telephone'] }}@endif
        </td>
    </tr>
</table>
