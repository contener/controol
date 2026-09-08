<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-slate-900 text-sm border-t-[6px] border-blue-700 print:border-t-4">
        <div class="p-8 print:p-6">
        <div class="flex justify-between items-start gap-6 pb-7 border-b-2 border-slate-100">
            <div>
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-16 w-auto object-contain mb-3" alt="Logo">
                <h1 class="text-2xl font-extrabold tracking-tight">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                <div v-if="apercu.boutique.adresse || apercu.boutique.ville || apercu.boutique.pays" class="mt-2.5 flex items-start gap-1.5 text-slate-700">
                    <span class="text-blue-700 leading-none">📍</span>
                    <div class="leading-snug">
                        <div v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</div>
                        <div v-if="apercu.boutique.ville || apercu.boutique.pays">{{ [apercu.boutique.ville, apercu.boutique.pays].filter(Boolean).join(', ') }}</div>
                    </div>
                </div>
                <div class="mt-2 text-slate-500 text-xs space-y-0.5">
                    <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                    <div v-if="apercu.boutique.nui" class="font-medium text-slate-500">NUI : {{ apercu.boutique.nui }}</div>
                </div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-[11px] font-bold uppercase tracking-widest text-blue-700">Facture</div>
                <h2 class="text-2xl font-extrabold tracking-tight mt-0.5">{{ apercu.meta.numero }}</h2>
                <div class="text-slate-500 mt-2.5 space-y-0.5 text-xs">
                    <div>Émission : {{ apercu.meta.date_emission || '—' }}</div>
                    <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                </div>
            </div>
        </div>

        <div class="mt-8 bg-slate-50 border-l-4 border-blue-700 rounded-r-lg p-5">
            <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Facturé à</div>
            <div class="font-medium">{{ apercu.client.nom }}</div>
            <div class="text-slate-500 space-y-0.5">
                <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
            </div>
        </div>

        <div class="mt-8 rounded-lg border border-slate-200 overflow-hidden">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-slate-900 text-white text-xs uppercase tracking-wide">
                        <th class="text-left py-3 px-4 font-semibold">Désignation</th>
                        <th class="text-right py-3 px-4 font-semibold">Qté</th>
                        <th class="text-right py-3 px-4 font-semibold">P.U. HT</th>
                        <th class="text-right py-3 px-4 font-semibold">TVA %</th>
                        <th class="text-right py-3 px-4 font-semibold">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, i) in apercu.lignes" :key="i" :class="i % 2 === 1 ? 'bg-slate-50' : 'bg-white'" class="border-b border-slate-100">
                        <td class="py-3 px-4">
                            <div>{{ ligne.designation || '—' }}</div>
                            <div v-if="ligne.description" class="text-xs text-slate-400">{{ ligne.description }}</div>
                        </td>
                        <td class="py-3 px-4 text-right">{{ ligne.quantite }}</td>
                        <td class="py-3 px-4 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                        <td class="py-3 px-4 text-right">{{ ligne.tva_taux }}</td>
                        <td class="py-3 px-4 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                    </tr>
                    <tr v-if="apercu.lignes.length === 0">
                        <td colspan="5" class="py-6 text-center text-slate-400">Aucune ligne pour le moment.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6 ml-auto w-72">
            <div class="space-y-1.5 text-slate-500">
                <div class="flex justify-between"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
            </div>
            <div class="mt-3 flex justify-between items-center bg-blue-700 text-white rounded-lg px-4 py-3">
                <span class="font-semibold">Total TTC</span>
                <span class="text-lg font-bold">{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-8">
            <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Notes</div>
            <p class="text-slate-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div v-if="apercu.garantie" class="mt-8 pt-4 border-t border-dashed border-slate-200">
            <p class="text-[11px] text-slate-400 italic leading-relaxed"><span class="font-semibold not-italic">Garantie : </span>{{ apercu.garantie }}</p>
        </div>
        <div v-if="apercu.boutique.note_pied_facture" class="mt-3 pt-3" :class="!apercu.garantie ? 'border-t border-dashed border-slate-200' : ''">
            <p class="text-[11px] text-slate-400 italic leading-relaxed">{{ apercu.boutique.note_pied_facture }}</p>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-200 flex justify-between items-end text-xs text-slate-400">
            <div>Merci de votre confiance.</div>
            <div class="text-center">
                <div class="w-32 border-b border-slate-300 mb-1">&nbsp;</div>
                Signature
            </div>
        </div>
        </div>
    </div>
</template>
