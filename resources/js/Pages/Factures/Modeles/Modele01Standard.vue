<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-gray-900 text-sm p-8 print:p-0">
        <div class="flex justify-between items-start gap-6 pb-6 border-b border-gray-200">
            <div>
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-14 w-auto object-contain mb-2" alt="Logo">
                <h1 class="text-lg font-bold">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                <div class="text-gray-500 mt-1 space-y-0.5">
                    <div v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</div>
                    <div v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}</div>
                    <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold">FACTURE {{ apercu.meta.numero }}</h2>
                <div class="text-gray-500 mt-1 space-y-0.5">
                    <div>Émission : {{ apercu.meta.date_emission || '—' }}</div>
                    <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                </div>
            </div>
        </div>

        <div class="mt-6 bg-gray-50 rounded-md p-4">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Facturé à</div>
            <div class="font-medium">{{ apercu.client.nom }}</div>
            <div class="text-gray-500 space-y-0.5">
                <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
            </div>
        </div>

        <table class="w-full mt-6 border-collapse">
            <thead>
                <tr class="border-b-2 border-gray-800 text-xs uppercase text-gray-500">
                    <th class="text-left py-2">Désignation</th>
                    <th class="text-right py-2">Qté</th>
                    <th class="text-right py-2">P.U. HT</th>
                    <th class="text-right py-2">TVA %</th>
                    <th class="text-right py-2">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-b border-gray-100">
                    <td class="py-2">
                        <div>{{ ligne.designation || '—' }}</div>
                        <div v-if="ligne.description" class="text-xs text-gray-400">{{ ligne.description }}</div>
                    </td>
                    <td class="py-2 text-right">{{ ligne.quantite }}</td>
                    <td class="py-2 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-2 text-right">{{ ligne.tva_taux }}</td>
                    <td class="py-2 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="5" class="py-6 text-center text-gray-400">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 ml-auto w-64 space-y-1">
            <div class="flex justify-between text-gray-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
            <div class="flex justify-between text-gray-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
            <div class="flex justify-between text-gray-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
            <div class="flex justify-between font-bold text-base border-t-2 border-gray-800 pt-1"><span>Total TTC</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
        </div>

        <div v-if="apercu.notes" class="mt-8">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</div>
            <p class="text-gray-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div class="mt-12 pt-6 border-t border-gray-200 flex justify-between text-xs text-gray-400">
            <div>Merci de votre confiance.</div>
            <div class="text-center">
                <div class="w-32 border-b border-gray-300 mb-1">&nbsp;</div>
                Signature
            </div>
        </div>
    </div>
</template>
