{{-- Récapitulatif Avance / Reste à payer, propre aux Reçus (montant_avance renseigné).
     Partagé par les 10 modèles pour éviter de dupliquer ce bloc 10 fois — voir
     _totaux.blade.php pour le même principe appliqué aux totaux standards. --}}
@if(($apercu['meta']['type'] ?? null) === 'recu' && $apercu['totaux']['montant_avance'] !== null)
    <table class="totaux recap-paiement-recu">
        <tr><td>Montant total à verser</td><td class="text-right">{{ number_format($apercu['totaux']['total_ttc'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        <tr><td>Avance versée</td><td class="text-right">{{ number_format($apercu['totaux']['montant_avance'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        <tr class="total-final"><td>Reste à payer</td><td class="text-right">{{ number_format($apercu['totaux']['reste_a_payer'], 2, ',', ' ') }} {{ $apercu['meta']['devise'] }}</td></tr>
        @if($apercu['meta']['date_echeance'])
            <tr><td colspan="2" style="font-size: 9px; font-weight: normal; color: #6b7280; padding-top: 6px; text-align: right;">Reste à régler avant le {{ $apercu['meta']['date_echeance'] }}</td></tr>
        @endif
    </table>
@endif
