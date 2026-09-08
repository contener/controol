<script setup>
import { computed } from 'vue';
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
});

const { formatMontant } = useCurrencyFormat();

const montantPaye = computed(() => (props.apercu.meta.statut === 'payee' ? props.apercu.totaux.total_ttc : 0));
const soldeRestant = computed(() => props.apercu.totaux.total_ttc - montantPaye.value);
</script>

<template>
    <div class="bg-white text-slate-900 text-sm print:p-0">
        <div class="bg-slate-900 px-10 py-7 border-b-4 border-amber-500">
            <div class="flex justify-between items-center gap-6">
                <div class="flex items-center gap-4">
                    <img v-if="apercu.boutique.logo_url" :src="apercu.boutique.logo_url" class="h-14 w-auto object-contain bg-white rounded p-1" alt="Logo">
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-wide">{{ apercu.boutique.nom || 'Ma boutique' }}</h1>
                        <div v-if="apercu.boutique.ville || apercu.boutique.pays" class="text-slate-300 text-xs mt-1.5">
                            <span>📍</span> {{ apercu.boutique.ville }}<span v-if="apercu.boutique.ville && apercu.boutique.pays">, </span>{{ apercu.boutique.pays }}
                        </div>
                    </div>
                </div>
                <div class="text-right text-white">
                    <div class="text-xs uppercase tracking-widest text-amber-400 font-semibold">Facture</div>
                    <div class="text-xl font-bold">{{ apercu.meta.numero }}</div>
                </div>
            </div>
        </div>

        <div class="p-10 print:p-0 print:pt-6">
            <table class="w-full text-xs mb-6">
                <tbody>
                    <tr>
                        <td class="text-slate-400 pr-2 py-0.5">Date d'émission</td>
                        <td class="font-medium pr-8 py-0.5">{{ apercu.meta.date_emission || '—' }}</td>
                        <td class="text-slate-400 pr-2 py-0.5">Date d'échéance</td>
                        <td class="font-medium py-0.5">{{ apercu.meta.date_echeance || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="grid grid-cols-2 gap-0 border border-slate-300 rounded-md overflow-hidden">
                <div class="p-4 border-r border-slate-300">
                    <div class="text-xs font-bold text-slate-900 uppercase tracking-wide mb-2">Émetteur</div>
                    <div class="font-semibold">{{ apercu.boutique.nom }}</div>
                    <div v-if="apercu.boutique.adresse || apercu.boutique.ville" class="text-slate-600 text-xs mt-1.5">
                        <span>📍</span>
                        <span v-if="apercu.boutique.adresse">{{ apercu.boutique.adresse }}<span v-if="apercu.boutique.ville">, </span></span><span v-if="apercu.boutique.ville">{{ apercu.boutique.ville }}</span><span v-if="apercu.boutique.pays">, {{ apercu.boutique.pays }}</span>
                    </div>
                    <div class="text-slate-500 text-xs space-y-0.5 mt-1.5">
                        <div v-if="apercu.boutique.telephone">Tél : {{ apercu.boutique.telephone }}</div>
                        <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                        <div v-if="apercu.boutique.nui" class="font-medium text-slate-600">NUI : {{ apercu.boutique.nui }}</div>
                    </div>
                </div>
                <div class="p-4">
                    <div class="text-xs font-bold text-slate-900 uppercase tracking-wide mb-2">Facturé à</div>
                    <div class="font-semibold">{{ apercu.client.nom }}</div>
                    <div class="text-slate-500 text-xs space-y-0.5 mt-1">
                        <div v-if="apercu.client.adresse">{{ apercu.client.adresse }}</div>
                        <div v-if="apercu.client.ville">{{ apercu.client.ville }} {{ apercu.client.pays }}</div>
                        <div v-if="apercu.client.email">{{ apercu.client.email }}</div>
                        <div v-if="apercu.client.telephone">{{ apercu.client.telephone }}</div>
                        <div v-if="apercu.client.numero_fiscal">Réf. : {{ apercu.client.numero_fiscal }}</div>
                    </div>
                </div>
            </div>

            <table class="w-full mt-6 border-collapse">
                <thead>
                    <tr class="border-b-2 border-slate-900 text-xs uppercase text-slate-500">
                        <th class="text-left py-2">Désignation</th>
                        <th class="text-right py-2">Qté</th>
                        <th class="text-right py-2">P.U. HT</th>
                        <th class="text-right py-2">TVA %</th>
                        <th class="text-right py-2">Total TTC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, i) in apercu.lignes" :key="i" class="border-b border-slate-100" :class="i % 2 === 1 ? 'bg-slate-50' : ''">
                        <td class="py-2">
                            <div>{{ ligne.designation || '—' }}</div>
                            <div v-if="ligne.description" class="text-xs text-slate-400">{{ ligne.description }}</div>
                        </td>
                        <td class="py-2 text-right">{{ ligne.quantite }}</td>
                        <td class="py-2 text-right">{{ formatMontant(ligne.prix_unitaire) }}</td>
                        <td class="py-2 text-right">{{ ligne.tva_taux }}</td>
                        <td class="py-2 text-right">{{ formatMontant(ligne.montant_ttc) }}</td>
                    </tr>
                    <tr v-if="apercu.lignes.length === 0">
                        <td colspan="5" class="py-6 text-center text-slate-400">Aucune ligne pour le moment.</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-4 ml-auto w-72 space-y-1">
                <div class="flex justify-between text-slate-500"><span>Sous-total HT</span><span>{{ formatMontant(apercu.totaux.sous_total, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Remise</span><span>- {{ formatMontant(apercu.totaux.remise, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>TVA</span><span>{{ formatMontant(apercu.totaux.total_tva, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between items-center font-bold text-lg bg-slate-900 text-white rounded-md px-3 py-2.5 mt-1 border-t-2 border-amber-500"><span>Total TTC</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between text-slate-500 pt-1"><span>Montant payé</span><span>{{ formatMontant(montantPaye, apercu.meta.devise) }}</span></div>
                <div class="flex justify-between font-semibold" :class="soldeRestant > 0 ? 'text-amber-600' : 'text-green-600'"><span>Solde restant</span><span>{{ formatMontant(soldeRestant, apercu.meta.devise) }}</span></div>
            </div>

            <div class="mt-8 border border-slate-200 rounded-md p-4">
                <div class="text-xs font-bold text-slate-900 uppercase mb-1">Méthode de paiement</div>
                <div class="text-slate-500 text-xs space-y-0.5">
                    <div v-if="apercu.boutique.whatsapp">WhatsApp : {{ apercu.boutique.whatsapp }}</div>
                    <div v-if="apercu.boutique.email">{{ apercu.boutique.email }}</div>
                </div>
            </div>

            <div class="mt-4 text-xs text-slate-400">
                <div class="font-semibold text-slate-500 uppercase mb-1">Conditions</div>
                Facture payable selon les modalités convenues. Merci de bien vouloir mentionner le numéro de facture lors de tout règlement.
            </div>

            <div v-if="apercu.notes" class="mt-4">
                <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Notes</div>
                <p class="text-slate-600 whitespace-pre-line">{{ apercu.notes }}</p>
            </div>

            <div v-if="apercu.garantie" class="mt-6 pt-3 border-t border-slate-100 text-[11px] text-slate-400 italic">
                <span class="font-semibold not-italic">Garantie : </span>{{ apercu.garantie }}
            </div>
            <div v-if="apercu.boutique.note_pied_facture" class="mt-2 pt-2 text-[11px] text-slate-400 italic" :class="!apercu.garantie ? 'border-t border-slate-100 pt-3 mt-6' : ''">
                {{ apercu.boutique.note_pied_facture }}
            </div>

            <div class="mt-12 grid grid-cols-2 gap-8 text-xs text-slate-500">
                <div class="border border-slate-200 rounded-md p-4">
                    <div class="font-semibold text-slate-900 mb-6">Pour {{ apercu.boutique.nom || 'la boutique' }}</div>
                    <div class="border-b border-slate-300 mb-1">&nbsp;</div>
                    <div>Signature &amp; date</div>
                </div>
                <div class="border border-slate-200 rounded-md p-4">
                    <div class="font-semibold text-slate-900 mb-6">Pour {{ apercu.client.nom || 'le client' }}</div>
                    <div class="border-b border-slate-300 mb-1">&nbsp;</div>
                    <div>Signature &amp; date</div>
                </div>
            </div>
        </div>
    </div>
</template>
