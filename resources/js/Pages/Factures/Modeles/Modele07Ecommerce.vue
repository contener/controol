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
    brouillon: 'bg-gray-100 text-gray-700',
    envoyee: 'bg-yellow-100 text-yellow-800',
    payee: 'bg-green-100 text-green-800',
    annulee: 'bg-red-100 text-red-800',
};

const statutLabel = computed(() => statutLabels[props.apercu.meta.statut] || props.apercu.meta.statut || '—');
const statutClasse = computed(() => statutClasses[props.apercu.meta.statut] || 'bg-gray-100 text-gray-700');
</script>

<template>
    <div class="bg-white text-gray-900 text-sm p-8 print:p-0">
        <div class="flex justify-between items-start gap-6">
            <div class="flex items-center gap-3">
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-16 w-16 object-contain rounded-lg border border-gray-100 p-1" alt="Logo">
                <div>
                    <h1 class="text-lg font-bold text-violet-700">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                    <div class="text-gray-500 text-xs mt-1 space-y-0.5">
                        <div v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</div>
                        <div v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}, {{ apercu.boutique.pays }}</div>
                        <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <span class="inline-block text-xs font-bold px-3 py-1 rounded-full" :class="statutClasse">{{ statutLabel }}</span>
                <div class="mt-2 bg-violet-50 border border-violet-100 rounded-lg px-4 py-2">
                    <div class="text-xs uppercase tracking-wide text-violet-500 font-semibold">Commande / Facture</div>
                    <div class="font-bold text-violet-800">{{ apercu.meta.numero }}</div>
                    <div class="text-gray-500 text-xs mt-1 space-y-0.5">
                        <div>Émise : {{ apercu.meta.date_emission || '—' }}</div>
                        <div v-if="apercu.meta.date_echeance">Échéance : {{ apercu.meta.date_echeance }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-violet-600 uppercase mb-1 flex items-center gap-1">
                    <span>Facturé à</span>
                </div>
                <div class="font-medium">{{ apercu.client.nom }}</div>
                <div class="text-gray-500 space-y-0.5 mt-1">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                    <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                </div>
            </div>
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="text-xs font-semibold text-violet-600 uppercase mb-1">Livraison</div>
                <div class="font-medium">{{ apercu.client.nom }}</div>
                <div class="text-gray-500 space-y-0.5 mt-1">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="!apercu.client.adresse && !apercu.client.ville" class="italic text-gray-400">Adresse de livraison non renseignée.</div>
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
                        <div v-if="ligne.description" class="text-xs text-gray-400">{{ ligne.description }}</div>
                    </td>
                    <td class="py-2 px-2 text-right">{{ ligne.quantite }}</td>
                    <td class="py-2 px-2 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-2 px-2 text-right text-red-500">{{ ligne.remise_ligne ? '- ' + formatMontant(ligne.remise_ligne) : '—' }}</td>
                    <td class="py-2 px-2 text-right font-medium">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="5" class="py-6 text-center text-gray-400">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-6 flex justify-between items-start gap-6">
            <div class="border border-dashed border-gray-300 rounded-lg px-4 py-3 text-center text-xs text-gray-400 w-40 shrink-0">
                <div class="w-16 h-16 mx-auto border border-gray-200 rounded flex items-center justify-center mb-1 text-gray-300">QR</div>
                Lien de commande
            </div>
            <div class="w-64 space-y-1">
                <div class="flex justify-between text-gray-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between font-bold text-base bg-violet-600 text-white rounded px-2 py-1.5"><span>Total</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-6">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</div>
            <p class="text-gray-600 whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div class="mt-10 pt-4 border-t border-gray-200 text-center text-xs text-gray-400">
            Merci pour votre commande !
        </div>
    </div>
</template>
