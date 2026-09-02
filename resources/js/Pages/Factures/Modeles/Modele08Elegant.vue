<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-gray-900 text-sm px-16 py-14 print:p-0 font-light">
        <div class="text-center">
            <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-12 w-auto object-contain mx-auto mb-4" alt="Logo">
            <h1 class="font-serif text-2xl tracking-widest uppercase">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
            <div class="text-gray-400 text-xs mt-2 tracking-wide space-x-2">
                <span v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</span>
                <span v-if="apercu.boutique.ville">· {{ apercu.boutique.ville }}</span>
                <span v-if="apercu.boutique.email">· {{ apercu.boutique.email }}</span>
            </div>
        </div>

        <div class="w-10 h-px bg-gray-300 mx-auto my-10"></div>

        <div class="flex justify-between items-start text-xs">
            <div>
                <div class="uppercase tracking-widest text-gray-400 mb-2">Facturé à</div>
                <div class="text-base font-normal">{{ apercu.client.nom }}</div>
                <div class="text-gray-400 mt-1 space-y-0.5">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="uppercase tracking-widest text-gray-400 mb-2">Facture</div>
                <div class="text-base font-normal">{{ apercu.meta.numero }}</div>
                <div class="text-gray-400 mt-1 space-y-0.5">
                    <div>{{ apercu.meta.date_emission || '—' }}</div>
                    <div v-if="apercu.meta.date_echeance">Échéance {{ apercu.meta.date_echeance }}</div>
                </div>
            </div>
        </div>

        <table class="w-full mt-14 border-collapse">
            <thead>
                <tr class="border-b border-gray-300 text-xs uppercase tracking-widest text-gray-400">
                    <th class="text-left py-3 font-normal">Désignation</th>
                    <th class="text-right py-3 font-normal">Qté</th>
                    <th class="text-right py-3 font-normal">Prix</th>
                    <th class="text-right py-3 font-normal">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-b border-gray-100">
                    <td class="py-4">
                        <div>{{ ligne.designation || '—' }}</div>
                        <div v-if="ligne.description" class="text-xs text-gray-400 mt-0.5">{{ ligne.description }}</div>
                    </td>
                    <td class="py-4 text-right text-gray-500">{{ ligne.quantite }}</td>
                    <td class="py-4 text-right text-gray-500">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-4 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="4" class="py-10 text-center text-gray-300">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-16 text-center">
            <div class="uppercase tracking-widest text-gray-400 text-xs mb-2">Total à payer</div>
            <div class="font-serif text-4xl">{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</div>
            <div class="text-gray-400 text-xs mt-3 space-x-4">
                <span>Sous-total {{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span>
                <span>TVA {{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span>
                <span v-if="apercu.totaux.remise">Remise - {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-14 text-center text-gray-400 text-xs">
            <p class="whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div class="mt-20 flex justify-between items-end text-xs text-gray-400">
            <div class="tracking-wide">Merci de votre confiance.</div>
            <div class="text-center">
                <div class="w-36 border-b border-gray-200 mb-1">&nbsp;</div>
                <span class="tracking-widest uppercase">Signature</span>
            </div>
        </div>
    </div>
</template>
