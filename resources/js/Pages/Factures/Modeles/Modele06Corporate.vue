<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div class="bg-white text-gray-900 text-sm print:p-0">
        <!-- En-tête corporate -->
        <div class="bg-slate-800 text-white px-10 py-6 flex justify-between items-center gap-6 print:px-0 border-b-4 border-amber-500">
            <div class="flex items-center gap-4">
                <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-12 w-auto object-contain bg-white/95 rounded p-1" alt="Logo">
                <div class="text-xl font-bold tracking-wide">{{ apercu.boutique.nom || 'Ma boutique' }}</div>
            </div>
            <div class="text-right border border-white/30 rounded-md px-5 py-2.5 shrink-0 bg-white/5">
                <div class="text-[10px] uppercase tracking-widest text-slate-300">Référence</div>
                <div class="font-bold text-lg">FACTURE N° {{ apercu.meta.numero }}</div>
            </div>
        </div>

        <div class="p-8 print:p-0 print:pt-6">
            <div class="flex justify-end gap-8 text-xs text-gray-500 mb-6">
                <div>Date d'émission : <span class="font-medium text-gray-700">{{ apercu.meta.date_emission || '—' }}</span></div>
                <div v-if="apercu.meta.date_echeance">Date d'échéance : <span class="font-medium text-gray-700">{{ apercu.meta.date_echeance }}</span></div>
            </div>

            <!-- Émetteur / Client : informations clairement séparées -->
            <div class="grid grid-cols-2 gap-4">
                <div class="border border-gray-300 rounded-md p-4">
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2 pb-1 border-b border-gray-200">Émetteur</div>
                    <div class="font-medium">{{ apercu.boutique.nom || 'Ma boutique' }}</div>
                    <div v-if="apercu.boutique.adresse || apercu.boutique.ville" class="text-gray-600 mt-1.5 text-xs">
                        <span>📍</span>
                        <span v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}<span v-if="apercu.boutique.ville">, </span></span><span v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}</span><span v-if="apercu.boutique.pays">, {{ apercu.boutique.pays }}</span>
                    </div>
                    <div class="text-gray-500 mt-1.5 space-y-0.5">
                        <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                        <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                        <div v-if="apercu.boutique.nui" class="font-medium text-gray-600">NUI : {{ apercu.boutique.nui }}</div>
                    </div>
                </div>
                <div class="border border-gray-300 rounded-md p-4">
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2 pb-1 border-b border-gray-200">Client</div>
                    <div class="font-medium">{{ apercu.client.nom }}</div>
                    <div class="text-gray-500 mt-1 space-y-0.5">
                        <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                        <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                        <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                        <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                        <div v-if="apercu.client.numero_fiscal">N° fiscal : {{ apercu.client.numero_fiscal }}</div>
                    </div>
                </div>
            </div>

            <!-- Tableau détaillé avec remises et taxes -->
            <table class="w-full mt-6 border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 uppercase text-gray-500 border border-gray-300">
                        <th class="text-left py-2 px-2 border border-gray-300">Désignation</th>
                        <th class="text-right py-2 px-2 border border-gray-300">Qté</th>
                        <th class="text-right py-2 px-2 border border-gray-300">P.U. HT</th>
                        <th class="text-right py-2 px-2 border border-gray-300">Remise</th>
                        <th class="text-right py-2 px-2 border border-gray-300">TVA %</th>
                        <th class="text-right py-2 px-2 border border-gray-300">Montant HT</th>
                        <th class="text-right py-2 px-2 border border-gray-300">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, i) in apercu.lignes" :key="i" :class="i % 2 === 1 ? 'bg-gray-50' : ''">
                        <td class="py-2 px-2 border border-gray-200">
                            <div>{{ ligne.designation || '—' }}</div>
                            <div v-if="ligne.description" class="text-gray-400">{{ ligne.description }}</div>
                        </td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ ligne.quantite }}</td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ formatMontant(ligne.prix_unitaire) }}</td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ formatMontant(ligne.remise_ligne) }}</td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ ligne.tva_taux }}</td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ formatMontant(ligne.montant_ht) }}</td>
                        <td class="py-2 px-2 text-right border border-gray-200">{{ formatMontant(ligne.montant_ttc) }}</td>
                    </tr>
                    <tr v-if="apercu.lignes.length === 0">
                        <td colspan="7" class="py-6 text-center text-gray-400 border border-gray-200">Aucune ligne pour le moment.</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 ml-auto w-72 space-y-1.5">
                <div class="flex justify-between text-gray-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-gray-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between items-center font-bold text-lg bg-slate-800 text-white rounded-md px-3 py-2.5 mt-2"><span>Total TTC</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
            </div>

            <!-- Conditions générales -->
            <div class="mt-8 border-t border-gray-200 pt-4 text-xs text-gray-500">
                <div class="font-semibold text-gray-600 uppercase mb-1">Conditions générales</div>
                <p>Facture payable sous 30 jours. Tout retard de paiement pourra entraîner des pénalités.</p>
            </div>

            <!-- Coordonnées de paiement -->
            <div class="mt-4 border border-gray-200 rounded-md p-4">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Coordonnées de paiement</div>
                <div class="text-gray-500 space-y-0.5">
                    <div v-if="apercu.boutique.whatsapp">WhatsApp : {{ apercu.boutique.whatsapp }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                </div>
            </div>

            <div v-if="apercu.notes" class="mt-6">
                <div class="text-xs font-semibold text-gray-500 uppercase mb-1">Notes</div>
                <p class="text-gray-600 whitespace-pre-line">{{ apercu.notes }}</p>
            </div>

            <div v-if="apercu.boutique.note_pied_facture" class="mt-6 pt-3 border-t border-gray-100 text-[11px] text-gray-400 italic">
                {{ apercu.boutique.note_pied_facture }}
            </div>

            <div class="mt-10 pt-4 border-t border-gray-200 flex justify-between text-xs text-gray-400">
                <div>Document généré électroniquement.</div>
                <div class="text-center">
                    <div class="w-32 border-b border-gray-300 mb-1">&nbsp;</div>
                    Cachet et signature
                </div>
            </div>
        </div>
    </div>
</template>
