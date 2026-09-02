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
