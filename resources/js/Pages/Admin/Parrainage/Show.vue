<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    parrain: Object,
    filleuls: Array,
    commissions: Array,
    reglements: Array,
    statistiques: Object,
});

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' FCFA';
const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });

const statutPaiementLabels = { valide: 'Validé', en_attente: 'En attente', non_paye: 'Non payé' };
const statutPaiementClasses = {
    valide: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    en_attente: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    non_paye: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
};

// Règlement
const formReglement = useForm({
    montant: '',
    methode_paiement: '',
    reference_transaction: '',
    notes: '',
});
const confirmationOuverte = ref(false);
const demanderConfirmation = () => {
    if (!formReglement.montant || Number(formReglement.montant) <= 0) return;
    confirmationOuverte.value = true;
};
const validerReglement = () => {
    formReglement.post(route('admin.parrainage.reglements.store', props.parrain.id), {
        preserveScroll: true,
        onSuccess: () => { formReglement.reset(); confirmationOuverte.value = false; },
        onError: () => { confirmationOuverte.value = false; },
    });
};

// Annulation d'une commission
const commissionAAnnuler = ref(null);
const formAnnulation = useForm({ motif: '' });
const ouvrirAnnulation = (commission) => {
    formAnnulation.reset();
    formAnnulation.clearErrors();
    commissionAAnnuler.value = commission;
};
const validerAnnulation = () => {
    formAnnulation.post(route('admin.parrainage.commissions.annuler', commissionAAnnuler.value.id), {
        preserveScroll: true,
        onSuccess: () => { commissionAAnnuler.value = null; },
    });
};
</script>

<template>
    <AppLayout title="Administration — Parrainage">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🤝 Parrainage — {{ parrain.name }}</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <Link :href="route('admin.parrainage.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">&larr; Retour à la liste des parrains</Link>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Comptes créés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.comptes_crees }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Paiements commencés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.paiements_commences }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Paiements validés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.paiements_valides }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Gains générés</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatMontant(statistiques.gains_generes) }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Solde disponible</div>
                        <div class="mt-1 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ formatMontant(statistiques.solde_disponible) }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Déjà payés</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatMontant(statistiques.gains_payes) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">💸 Régler les commissions</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <InputLabel for="montant" value="Montant (FCFA) *" />
                            <TextInput id="montant" v-model="formReglement.montant" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                            <InputError :message="formReglement.errors.montant" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="methode" value="Méthode de paiement" />
                            <TextInput id="methode" v-model="formReglement.methode_paiement" type="text" class="mt-1 block w-full" placeholder="Mobile Money, virement..." />
                        </div>
                        <div>
                            <InputLabel for="reference" value="Référence de transaction" />
                            <TextInput id="reference" v-model="formReglement.reference_transaction" type="text" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="notes" value="Notes" />
                            <TextInput id="notes" v-model="formReglement.notes" type="text" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <PrimaryButton :disabled="formReglement.processing" @click="demanderConfirmation">Régler les commissions</PrimaryButton>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Solde disponible : {{ formatMontant(statistiques.solde_disponible) }}. Un règlement partiel est possible.</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="p-6 pb-0"><h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Filleuls</h3></div>
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mt-4">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Filleul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date d'inscription</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut paiement</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="filleuls.length === 0">
                                <td colspan="3" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun filleul.</td>
                            </tr>
                            <tr v-for="filleul in filleuls" :key="filleul.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ filleul.nom }}
                                    <div class="text-xs text-slate-400 dark:text-slate-500">{{ filleul.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(filleul.date_inscription) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutPaiementClasses[filleul.statut_paiement]">{{ statutPaiementLabels[filleul.statut_paiement] }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="p-6 pb-0"><h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Commissions</h3></div>
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mt-4">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Filleul</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="commissions.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucune commission.</td>
                            </tr>
                            <tr v-for="commission in commissions" :key="commission.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(commission.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">{{ commission.filleul?.name ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-slate-100">{{ formatMontant(commission.montant_commission) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ commission.statut === 'annulee' ? 'Annulée' : 'Disponible' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button v-if="commission.statut !== 'annulee'" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="ouvrirAnnulation(commission)">Annuler</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="p-6 pb-0"><h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Historique des règlements</h3></div>
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mt-4">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Méthode</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Référence</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Traité par</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="reglements.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun règlement pour le moment.</td>
                            </tr>
                            <tr v-for="reglement in reglements" :key="reglement.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(reglement.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-slate-100">{{ formatMontant(reglement.montant) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ reglement.methode_paiement ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ reglement.reference_transaction ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ reglement.traiteur?.name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="confirmationOuverte" @close="confirmationOuverte = false">
            <template #title>Confirmer le règlement</template>
            <template #content>
                Vous êtes sur le point de régler <strong>{{ formatMontant(formReglement.montant || 0) }}</strong> au parrain
                <strong>{{ parrain.name }}</strong>. Cette opération sera enregistrée dans l'historique.
            </template>
            <template #footer>
                <SecondaryButton @click="confirmationOuverte = false">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="formReglement.processing" @click="validerReglement">Confirmer le règlement</PrimaryButton>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="!!commissionAAnnuler" @close="commissionAAnnuler = null">
            <template #title>Annuler cette commission</template>
            <template #content>
                <p class="mb-3">Commission de {{ formatMontant(commissionAAnnuler?.montant_commission ?? 0) }}. Cette action est réservée aux cas de remboursement ou de fraude.</p>
                <InputLabel for="motif" value="Motif *" />
                <textarea id="motif" v-model="formAnnulation.motif" rows="3" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                <InputError :message="formAnnulation.errors.motif" class="mt-2" />
            </template>
            <template #footer>
                <SecondaryButton @click="commissionAAnnuler = null">Fermer</SecondaryButton>
                <DangerButton class="ms-3" :disabled="formAnnulation.processing" @click="validerAnnulation">Annuler la commission</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
