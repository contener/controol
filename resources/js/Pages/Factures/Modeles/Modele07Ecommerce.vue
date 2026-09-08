<script setup>
import { computed } from 'vue';
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();

const statutLabels = {
    brouillon: 'Brouillon',
    envoyee: 'Envoyée',
    payee: 'Payée',
    annulee: 'Annulée',
};

const statutClasses = {
    brouillon: 'bg-slate-100 text-slate-700',
    envoyee: 'bg-yellow-100 text-yellow-800',
    payee: 'bg-green-100 text-green-800',
    annulee: 'bg-red-100 text-red-800',
};

const statutLabel = computed(() => statutLabels[props.apercu.meta.statut] || props.apercu.meta.statut || '—');
const statutClasse = computed(() => statutClasses[props.apercu.meta.statut] || 'bg-slate-100 text-slate-700');
</script>

<template>
    <div class="bg-white text-slate-900 text-sm p-8 print:p-0">
        <div class="flex justify-between items-start gap-6">
            <div class="flex items-center gap-4">
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-16 w-16 object-contain rounded-xl border border-slate-100 p-1" alt="Logo">
                <div>
                    <h1 class="text-2xl font-bold text-violet-700 tracking-tight">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                    <div v-if="apercu.boutique.adresse || apercu.boutique.ville" class="text-slate-600 text-xs mt-1.5 font-medium">
                        <span>📍</span>
                        <span v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}<span v-if="apercu.boutique.ville">, </span></span><span v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}</span><span v-if="apercu.boutique.pays">, {{ apercu.boutique.pays }}</span>
                    </div>
                    <div class="text-slate-500 text-xs mt-1 space-y-0.5">
                        <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                        <div v-if="apercu.boutique.nui">NUI : {{ apercu.boutique.nui }}</div>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-block text-xs font-bold px-3 py-1 rounded-full" :class="statutClasse">{{ statutLabel }}</span>
                <div class="mt-2 bg-violet-50 border border-violet-100 rounded-lg px-4 py-2.5">
                    <div class="text-xs uppercase tracking-wide text-violet-500 font-semibold">Commande / Facture</div>
                    <div class="font-bold text-violet-800 text-lg">{{ apercu.meta.numero }}</div>
                    <div class="text-slate-500 text-xs mt-1 space-y-0.5">
                        <div>Émise : {{ apercu.meta.date_emission || '—' }}</div>
                        <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4">
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-violet-600 uppercase mb-1 flex items-center gap-1">
                    <span>Facturé à</span>
                </div>
                <div class="font-medium">{{ apercu.client.nom }}</div>
                <div class="text-slate-500 space-y-0.5 mt-1">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                    <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                </div>
            </div>
            <div class="border border-slate-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-violet-600 uppercase mb-1">Livraison</div>
                <div class="font-medium">{{ apercu.client.nom }}</div>
                <div class="text-slate-500 space-y-0.5 mt-1">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="!apercu.client.adresse && !apercu.client.ville" class="italic text-slate-400">Adresse de livraison non renseignée.</div>
                </div>
            </div>
        </div>

        <table class="w-full mt-6 border-collapse">
            <thead>
                <tr class="bg-violet-600 text-white text-xs uppercase">
                    <th class="text-left py-2 px-2 rounded-l-md">Produit</th>
                    <th class="text-right py-2 px-2">Qté</th>
                    <th class="text-right py-2 px-2">Prix unitaire</th>
                    <th class="text-right py-2 px-2">Remise</th>
                    <th class="text-right py-2 px-2 rounded-r-md">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, i) in apercu.lignes" :key="i" :class="i % 2 === 1 ? 'bg-violet-50/50' : ''">
                    <td class="py-2 px-2">
                        <div>{{ ligne.designation || '—' }}</div>
                        <div v-if="ligne.description" class="text-xs text-slate-400">{{ ligne.description }}</div>
                    </td>
                    <td class="py-2 px-2 text-right">{{ ligne.quantite }}</td>
                    <td class="py-2 px-2 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-2 px-2 text-right text-red-500">{{ ligne.remise_ligne ? '- ' + formatMontant(ligne.remise_ligne) : '—' }}</td>
                    <td class="py-2 px-2 text-right font-medium">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="5" class="py-6 text-center text-slate-400">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-6 flex justify-between items-start gap-6">
            <div class="border border-dashed border-slate-300 rounded-lg px-4 py-3 text-center text-xs text-slate-400 w-40 shrink-0">
                <div class="w-16 h-16 mx-auto border border-slate-200 rounded flex items-center justify-center mb-1 text-slate-300">QR</div>
                Lien de commande
            </div>
            <div class="w-64 space-y-1">
                <div class="flex justify-between text-slate-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between items-center font-bold text-lg bg-violet-600 text-white rounded-lg px-3 py-2.5 mt-1"><span>Total</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-6">
            <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Notes</div>
            <p class="text-slate-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div v-if="apercu.garantie" class="mt-6 pt-3 border-t border-dashed border-slate-200 text-[11px] text-slate-400 italic text-center">
            <span class="font-semibold not-italic">Garantie : </span>{{ apercu.garantie }}
        </div>
        <div v-if="apercu.boutique.note_pied_facture" class="mt-2 pt-2 text-[11px] text-slate-400 italic text-center" :class="!apercu.garantie ? 'border-t border-dashed border-slate-200 pt-3 mt-6' : ''">
            {{ apercu.boutique.note_pied_facture }}
        </div>

        <div class="mt-10 pt-4 border-t border-slate-200 text-center text-xs text-slate-400">
            Merci pour votre commande !
        </div>
    </div>
</template>
