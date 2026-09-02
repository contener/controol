<table class="totaux">
    <tr><td>Sous-total HT</td><td class="text-right">{{ number_format($apercu['totaux']['sous_total'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
    <tr><td>TVA</td><td class="text-right">{{ number_format($apercu['totaux']['total_tva'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
    <tr><td>Remise</td><td class="text-right">- {{ number_format($apercu['totaux']['remise'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
    <tr class="total-final"><td>Total TTC</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
</table>
