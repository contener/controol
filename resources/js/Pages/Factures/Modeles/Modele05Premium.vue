<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-gray-900 text-sm font-serif p-10 print:p-0">
        <!-- En-tête élégant -->
        <div class="flex justify-between items-start gap-8 pb-7 border-b-2 border-amber-700/40">
            <div>
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-12 w-auto object-contain mb-3" alt="Logo">
                <h1 class="text-2xl font-semibold tracking-wide">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                <div v-if="apercu.boutique.adresse || apercu.boutique.ville || apercu.boutique.pays" class="mt-2.5 flex items-start gap-1.5 text-gray-600 text-xs">
                    <span class="text-amber-700">📍</span>
                    <span class="leading-snug">{{ [apercu.boutique.adresse, [apercu.boutique.ville, apercu.boutique.pays].filter(Boolean).join(', ')].filter(Boolean).join(' — ') }}</span>
                </div>
                <div class="text-gray-500 mt-1.5 space-y-0.5 text-xs">
                    <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                    <div v-if="apercu.boutique.nui" class="font-medium text-gray-600">NUI : {{ apercu.boutique.nui }}</div>
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-[11px] tracking-[0.25em] uppercase text-amber-700 font-semibold">Facture</div>
                <div class="text-xl font-semibold mt-1">{{ apercu.meta.numero }}</div>
                <div class="text-gray-500 mt-2 space-y-0.5 text-xs">
                    <div>Émission : {{ apercu.meta.date_emission || '—' }}</div>
                    <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                </div>
            </div>
        </div>

        <!-- Client -->
        <div class="mt-8">
            <div class="text-[11px] tracking-[0.2em] uppercase text-amber-700 font-semibold mb-2">Facturé à</div>
            <div class="font-medium text-base">{{ apercu.client.nom }}</div>
            <div class="text-gray-500 mt-1 space-y-0.5">
                <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                <div v-if="apercu.client.numero_fiscal">N° fiscal : {{ apercu.client.numero_fiscal }}</div>
            </div>
        </div>

        <!-- Tableau professionnel -->
        <table class="w-full mt-8 border-collapse">
            <thead>
                <tr class="border-b-2 border-amber-700/60 text-[11px] uppercase tracking-wide text-gray-500">
                    <th class="text-left py-2.5 font-semibold">Désignation</th>
                    <th class="text-right py-2.5 font-semibold">Qté</th>
                    <th class="text-right py-2.5 font-semibold">P.U. HT</th>
                    <th class="text-right py-2.5 font-semibold">TVA %</th>
                    <th class="text-right py-2.5 font-semibold">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-b border-gray-100">
                    <td class="py-3">
                        <div>{{ ligne.designation || '—' }}</div>
                        <div v-if="ligne.description" class="text-xs text-gray-400">{{ ligne.description }}</div>
                    </td>
                    <td class="py-3 text-right">{{ ligne.quantite }}</td>
                    <td class="py-3 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-3 text-right">{{ ligne.tva_taux }}</td>
                    <td class="py-3 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="5" class="py-6 text-center text-gray-400">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <!-- Résumé financier détaillé -->
        <div class="mt-5 ml-auto w-72">
            <div class="space-y-1.5 text-gray-500">
                <div class="flex justify-between"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
            </div>
            <div class="flex justify-between items-center font-semibold text-lg mt-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <span class="text-sm tracking-wide">Total TTC</span>
                <span class="text-amber-800 font-bold">{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span>
            </div>
        </div>

        <!-- Conditions de paiement -->
        <div class="mt-8 text-gray-500 text-xs">
            <div class="text-[11px] tracking-[0.2em] uppercase text-amber-700 font-semibold mb-1">Conditions de paiement</div>
            <div v-if="apercu.boutique.whatsapp">WhatsApp : {{ apercu.boutique.whatsapp }}</div>
            <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
        </div>

        <div v-if="apercu.notes" class="mt-6">
            <div class="text-[11px] tracking-[0.2em] uppercase text-amber-700 font-semibold mb-1">Notes</div>
            <p class="text-gray-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div v-if="apercu.garantie" class="mt-6 pt-4 border-t border-dashed border-amber-700/30">
            <p class="text-[11px] text-gray-400 italic leading-relaxed"><span class="font-semibold not-italic">Garantie : </span>{{ apercu.garantie }}</p>
        </div>
        <div v-if="apercu.boutique.note_pied_facture" class="mt-3 pt-3" :class="!apercu.garantie ? 'border-t border-dashed border-amber-700/30' : ''">
            <p class="text-[11px] text-gray-400 italic leading-relaxed">{{ apercu.boutique.note_pied_facture }}</p>
        </div>

        <!-- Signatures -->
        <div class="mt-14 flex justify-between gap-10 text-xs text-gray-500">
            <div class="flex-1 text-center">
                <div class="border-b border-gray-300 h-10">&nbsp;</div>
                <div class="mt-2 tracking-wide">Le fournisseur</div>
            </div>
            <div class="flex-1 text-center">
                <div class="border-b border-gray-300 h-10">&nbsp;</div>
                <div class="mt-2 tracking-wide">Le client</div>
            </div>
        </div>

        <div class="mt-10 pt-4 border-t border-amber-700/30 text-center text-xs text-gray-400 tracking-wide">
            Merci de votre confiance.
        </div>
    </div>
</template>
