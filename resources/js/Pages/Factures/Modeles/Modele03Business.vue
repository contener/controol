<script setup>
import { computed } from 'vue';
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();

const contactItems = computed(() => {
    const b = props.apercu.boutique;
    const items = [];
    if (b.telephone) items.push('Tél : ' + b.telephone);
    if (b.email) items.push(b.email);
    return items;
});
</script>

<template>
    <div class="bg-white text-slate-900 text-sm p-8 print:p-0">
        <!-- En-tête : logo mis en avant -->
        <div class="flex justify-between items-start gap-8 pb-7 border-b-2 border-emerald-700">
            <div class="flex-1">
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-20 w-auto object-contain mb-3" alt="Logo">
                <h1 class="text-2xl font-bold tracking-tight">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                <div v-if="apercu.boutique.adresse || apercu.boutique.ville || apercu.boutique.pays" class="mt-2.5 flex items-start gap-1.5 text-slate-700">
                    <span class="text-emerald-700 leading-none">📍</span>
                    <span class="leading-snug">
                        {{ [apercu.boutique.adresse, [apercu.boutique.ville, apercu.boutique.pays].filter(Boolean).join(', ')].filter(Boolean).join(' — ') }}
                    </span>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-slate-500">
                    <template v-for="(item, i) in contactItems" :key="i">
                        <span>{{ item }}</span>
                        <span v-if="i < contactItems.length - 1" class="text-slate-300">•</span>
                    </template>
                </div>
                <div v-if="apercu.boutique.nui" class="mt-1 text-xs font-medium text-slate-500">NUI : {{ apercu.boutique.nui }}</div>
            </div>
            <div class="shrink-0 text-right bg-emerald-50 border border-emerald-200 rounded-xl px-6 py-5">
                <div class="text-xs font-bold uppercase tracking-widest text-emerald-700">Facture</div>
                <div class="text-xl font-extrabold text-emerald-900 mt-0.5">{{ apercu.meta.numero }}</div>
                <div class="mt-2.5 text-slate-500 space-y-0.5">
                    <div>Émission : {{ apercu.meta.date_emission || '—' }}</div>
                    <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                </div>
            </div>
        </div>

        <!-- Client -->
        <div class="mt-7 border-l-4 border-emerald-500 bg-slate-50 rounded-r-lg p-5">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Facturé à</div>
            <div class="font-medium">{{ apercu.client.nom }}</div>
            <div class="text-slate-500 space-y-0.5">
                <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
            </div>
        </div>

        <!-- Tableau produits élégant -->
        <div class="mt-6 rounded-lg border border-slate-200 overflow-hidden">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-emerald-700 text-white text-xs uppercase tracking-wide">
                        <th class="text-left py-2.5 px-3 font-semibold">Désignation</th>
                        <th class="text-right py-2.5 px-3 font-semibold">Qté</th>
                        <th class="text-right py-2.5 px-3 font-semibold">P.U. HT</th>
                        <th class="text-right py-2.5 px-3 font-semibold">TVA %</th>
                        <th class="text-right py-2.5 px-3 font-semibold">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, i) in apercu.lignes" :key="i" :class="i % 2 === 1 ? 'bg-emerald-50/50' : 'bg-white'">
                        <td class="py-2.5 px-3">
                            <div>{{ ligne.designation || '—' }}</div>
                            <div v-if="ligne.description" class="text-xs text-slate-400">{{ ligne.description }}</div>
                        </td>
                        <td class="py-2.5 px-3 text-right">{{ ligne.quantite }}</td>
                        <td class="py-2.5 px-3 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                        <td class="py-2.5 px-3 text-right">{{ ligne.tva_taux }}</td>
                        <td class="py-2.5 px-3 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                    </tr>
                    <tr v-if="apercu.lignes.length === 0">
                        <td colspan="5" class="py-6 text-center text-slate-400">Aucune ligne pour le moment.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Résumé financier -->
        <div class="mt-5 ml-auto w-72">
            <div class="space-y-1 text-slate-500">
                <div class="flex justify-between"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
            </div>
            <div class="mt-3 flex justify-between items-center bg-emerald-600 text-white rounded-lg px-5 py-3.5">
                <span class="font-semibold">Total TTC</span>
                <span class="text-xl font-extrabold">{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span>
            </div>
        </div>

        <!-- Paiement -->
        <div class="mt-8 border border-slate-200 rounded-lg p-4">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Modalités de paiement</div>
            <div class="text-slate-500 space-y-0.5">
                <div v-if="apercu.boutique.whatsapp">WhatsApp : {{ apercu.boutique.whatsapp }}</div>
                <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-6">
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Notes</div>
            <p class="text-slate-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div v-if="apercu.garantie" class="mt-8 pt-4 border-t border-dashed border-emerald-200">
            <p class="text-[11px] text-slate-400 italic leading-relaxed"><span class="font-semibold not-italic">Garantie : </span>{{ apercu.garantie }}</p>
        </div>
        <div v-if="apercu.boutique.note_pied_facture" class="mt-3 pt-3" :class="!apercu.garantie ? 'border-t border-dashed border-emerald-200' : ''">
            <p class="text-[11px] text-slate-400 italic leading-relaxed">{{ apercu.boutique.note_pied_facture }}</p>
        </div>

        <div class="mt-8 pt-4 border-t border-slate-200 text-xs text-slate-400 text-center">
            Merci de votre confiance.
        </div>
    </div>
</template>
