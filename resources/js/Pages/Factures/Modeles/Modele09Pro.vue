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
    <div class="bg-white text-gray-900 text-sm p-8 print:p-0 border-t-4 border-blue-800">
        <div class="flex justify-between items-start gap-6 pb-4 border-b border-gray-200">
            <div class="flex items-center gap-3">
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-14 w-auto object-contain" alt="Logo">
                <div>
                    <h1 class="text-lg font-bold text-blue-900">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                    <div class="text-gray-500 text-xs mt-0.5 space-y-0.5">
                        <div v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}</div>
                        <div v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}, {{ apercu.boutique.pays }}</div>
                        <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                        <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                    </div>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-base font-bold uppercase tracking-wide text-blue-900">Facture</h2>
                <span class="inline-block text-xs font-bold px-2.5 py-0.5 rounded mt-1" :class="statutClasse">{{ statutLabel }}</span>
                <table class="mt-2 ml-auto text-xs">
                    <tbody>
                        <tr><td class="text-gray-400 pr-3 text-right">N° facture</td><td class="font-medium">{{ apercu.meta.numero }}</td></tr>
                        <tr v-if="apercu.client.numero_fiscal"><td class="text-gray-400 pr-3 text-right">Référence client</td><td class="font-medium">{{ apercu.client.numero_fiscal }}</td></tr>
                        <tr><td class="text-gray-400 pr-3 text-right">Émission</td><td class="font-medium">{{ apercu.meta.date_emission || '—' }}</td></tr>
                        <tr v-if="apercu.meta.date_echeance"><td class="text-gray-400 pr-3 text-right">Échéance</td><td class="font-medium">{{ apercu.meta.date_echeance }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded p-3">
                <div class="text-xs font-semibold text-blue-800 uppercase mb-1">Émetteur</div>
                <div class="font-medium">{{ apercu.boutique.nom }}</div>
                <div class="text-gray-500 text-xs space-y-0.5 mt-0.5">
                    <div v-if="apercu.boutique.whatsapp">WhatsApp : {{ apercu.boutique.whatsapp }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                </div>
            </div>
            <div class="border border-gray-200 rounded p-3">
                <div class="text-xs font-semibold text-blue-800 uppercase mb-1">Facturé à</div>
                <div class="font-medium">{{ apercu.client.nom }}</div>
                <div class="text-gray-500 text-xs space-y-0.5 mt-0.5">
                    <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                    <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                    <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                    <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                </div>
            </div>
        </div>

        <table class="w-full mt-4 border-collapse text-xs">
            <thead>
                <tr class="bg-blue-900 text-white uppercase">
                    <th class="text-left py-2 px-1.5">Désignation</th>
                    <th class="text-right py-2 px-1.5">Qté</th>
                    <th class="text-right py-2 px-1.5">P.U. HT</th>
                    <th class="text-right py-2 px-1.5">Remise</th>
                    <th class="text-right py-2 px-1.5">TVA %</th>
                    <th class="text-right py-2 px-1.5">Mont. HT</th>
                    <th class="text-right py-2 px-1.5">Mont. TTC</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-b border-gray-100">
                    <td class="py-2 px-1.5">
                        <div>{{ ligne.designation || '—' }}</div>
                        <div v-if="ligne.description" class="text-gray-400">{{ ligne.description }}</div>
                    </td>
                    <td class="py-2 px-1.5 text-right">{{ ligne.quantite }}</td>
                    <td class="py-2 px-1.5 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                    <td class="py-2 px-1.5 text-right">{{ ligne.remise_ligne ? formatMontant(ligne.remise_ligne) : '—' }}</td>
                    <td class="py-2 px-1.5 text-right">{{ ligne.tva_taux }}</td>
                    <td class="py-2 px-1.5 text-right">{{ formatMontant(ligne.montant_ht) }}</td>
                    <td class="py-2 px-1.5 text-right font-medium">{{ formatMontant(ligne.montant_ttc) }}</td>
                </tr>
                <tr v-if="apercu.lignes.length === 0">
                    <td colspan="7" class="py-6 text-center text-gray-400">Aucune ligne pour le moment.</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-4 flex justify-between items-start gap-6">
            <div class="text-xs text-gray-400 w-72">
                <div class="font-semibold text-gray-500 uppercase mb-1">Conditions</div>
                Paiement dû à la date d'échéance indiquée. Toute somme non réglée à l'échéance pourra donner lieu à des pénalités, conformément aux conditions générales de vente.
            </div>
            <div class="w-64 space-y-1 text-xs">
                <div class="flex justify-between text-gray-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between font-bold text-sm border-t-2 border-blue-900 pt-1"><span>Total TTC</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
            </div>
        </div>

        <div v-if="apercu.notes" class="mt-4">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</div>
            <p class="text-gray-600 text-xs whitespace-pre-line">{{ apercu.notes }}</p>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-end text-xs text-gray-400">
            <div>
                <div class="font-semibold text-gray-500 uppercase mb-1">Signature autorisée</div>
                <div class="w-40 border-b border-gray-300 mb-1">&nbsp;</div>
                Pour {{ apercu.boutique.nom || 'la boutique' }}
            </div>
        </div>
    </div>
</template>
