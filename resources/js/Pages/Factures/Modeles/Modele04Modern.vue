<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-gray-900 text-sm p-8 print:p-0">
        <!-- Grande zone d'en-tête façon hero SaaS -->
        <div class="rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 text-white px-8 py-10 text-center">
            <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-14 w-auto object-contain mx-auto mb-3" alt="Logo">
            <h1 class="text-2xl font-bold tracking-tight">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
            <p v-if="apercu.boutique.ville || apercu.boutique.pays" class="mt-1 text-violet-100 text-xs">
                {{ [apercu.boutique.ville, apercu.boutique.pays].filter(Boolean).join(', ') }}
            </p>
            <div class="mt-5 flex justify-center gap-2 flex-wrap">
                <span class="bg-white/15 rounded-full px-4 py-1.5 text-xs font-medium">Facture {{ apercu.meta.numero }}</span>
                <span v-if="apercu.meta.date_emission" class="bg-white/15 rounded-full px-4 py-1.5 text-xs font-medium">Émise le {{ apercu.meta.date_emission }}</span>
                <span v-if="apercu.meta.date_echeance" class="bg-white/15 rounded-full px-4 py-1.5 text-xs font-medium">Échéance {{ apercu.meta.date_echeance }}</span>
            </div>
        </div>

        <!-- Cartes : client / boutique -->
        <div class="mt-6 grid grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-200 shadow-sm p-4">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Facturé à</div>
                <div class="font-semibold">{{ apercu.client.nom }}</div>
                <div class="text-gray-500 space-y-0.5 mt-1">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                    <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 shadow-sm p-4">
                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Coordonnées</div>
                <div class="text-gray-500 space-y-0.5">
                    <div v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</div>
                    <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                    <div v-if="!apercu.boutique.adresse && !apercu.boutique.telephone && !apercu.boutique.email" class="text-gray-300">—</div>
                </div>
            </div>
        </div>

        <!-- Tableau épuré -->
        <div class="mt-4 rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="text-xs uppercase tracking-wide text-gray-400">
                        <th class="text-left py-3 px-4 font-medium">Désignation</th>
                        <th class="text-right py-3 px-4 font-medium">Qté</th>
                        <th class="text-right py-3 px-4 font-medium">P.U. HT</th>
                        <th class="text-right py-3 px-4 font-medium">TVA %</th>
                        <th class="text-right py-3 px-4 font-medium">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-t border-gray-100">
                        <td class="py-3 px-4">
                            <div>{{ ligne.designation || '—' }}</div>
                            <div v-if="ligne.description" class="text-xs text-gray-400">{{ ligne.description }}</div>
                        </td>
                        <td class="py-3 px-4 text-right">{{ ligne.quantite }}</td>
                        <td class="py-3 px-4 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                        <td class="py-3 px-4 text-right">{{ ligne.tva_taux }}</td>
                        <td class="py-3 px-4 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                    </tr>
                    <tr v-if="apercu.lignes.length === 0">
                        <td colspan="5" class="py-6 text-center text-gray-400">Aucune ligne pour le moment.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Résumé financier mis en avant -->
        <div class="mt-4 flex justify-between items-start gap-4">
            <div class="w-20 h-20 border border-dashed border-gray-300 rounded-lg flex items-center justify-center text-[10px] text-gray-300 text-center leading-tight shrink-0">
                QR Code
            </div>
            <div class="flex-1 max-w-xs ml-auto rounded-lg border border-gray-200 shadow-sm p-4">
                <div class="space-y-1 text-gray-500">
                    <div class="flex justify-between"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                    <div class="flex justify-between"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                    <div class="flex justify-between"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                </div>
                <div class="mt-3 flex justify-between items-center bg-violet-600 text-white rounded-lg px-4 py-3">
                    <span class="font-semibold">Total TTC</span>
                    <span class="text-lg font-bold">{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span>
                </div>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-6 rounded-lg border border-gray-200 shadow-sm p-4">
            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Notes</div>
            <p class="text-gray-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div class="mt-10 pt-4 border-t border-gray-100 text-xs text-gray-400 text-center tracking-wide">
            Merci de votre confiance.
        </div>
    </div>
</template>
