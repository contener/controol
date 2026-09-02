<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    paiement: Object,
});

const formatMontant = (montant, devise) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + devise;
const formatDate = (date) => (date ? new Date(date).toLocaleString('fr-FR') : '—');

const statutLabels = { en_attente: 'En attente', approuve: 'Approuvé', rejete: 'Rejeté' };
const statutClasses = {
    en_attente: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    approuve: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    rejete: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};

const actionLabels = { approbation: 'Approbation', rejet: 'Rejet' };

const showApprouver = ref(false);
const approuver = () => {
    router.post(route('admin.paiements.approuver', props.paiement.id), {}, {
        onFinish: () => { showApprouver.value = false; },
    });
};

const showRejeter = ref(false);
const formRejet = useForm({ motif: '' });
const rejeter = () => {
    formRejet.post(route('admin.paiements.rejeter', props.paiement.id), {
        onSuccess: () => { showRejeter.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="`Paiement #${paiement.id}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Paiement #{{ paiement.id }}</h2>
                <Link :href="route('admin.paiements.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Retour à la liste</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[paiement.statut]">{{ statutLabels[paiement.statut] }}</span>
                        <div v-if="paiement.statut === 'en_attente'" class="space-x-3">
                            <SecondaryButton @click="showRejeter = true">Refuser</SecondaryButton>
                            <PrimaryButton @click="showApprouver = true">Approuver</PrimaryButton>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Utilisateur</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.user.name }}</div>
                            <div class="text-gray-500 dark:text-gray-400">{{ paiement.user.email }}</div>
                            <div class="text-gray-500 dark:text-gray-400">{{ paiement.user.telephone ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Plan demandé</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.abonnement?.plan?.nom ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Montant</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatMontant(paiement.montant, paiement.devise) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Moyen de paiement</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.moyen_paiement }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Référence de transaction</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.reference_transaction ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Créé le</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(paiement.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Traité le</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(paiement.valide_at) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Traité par</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.validateur?.name ?? '—' }}</div>
                        </div>
                        <div v-if="paiement.motif_rejet" class="sm:col-span-2">
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Motif du refus</div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ paiement.motif_rejet }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Journal d'audit</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-if="paiement.audits.length === 0">
                                    <td class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucune action enregistrée pour l'instant.</td>
                                </tr>
                                <tr v-for="audit in paiement.audits" :key="audit.id">
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(audit.created_at) }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ actionLabels[audit.action] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ statutLabels[audit.statut_avant] }} → {{ statutLabels[audit.statut_apres] }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ audit.admin.name }}</td>
                                    <td class="px-6 py-3 text-sm text-gray-500 dark:text-gray-400">{{ audit.motif ?? '—' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-400 dark:text-gray-500">{{ audit.ip_address }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal :show="showApprouver" @close="showApprouver = false">
            <template #title>Confirmer la validation ?</template>
            <template #content>
                Vous êtes sur le point d'activer le plan
                <strong>{{ paiement.abonnement?.plan?.nom }}</strong>
                pour <strong>{{ paiement.user.name }}</strong>.
            </template>
            <template #footer>
                <SecondaryButton @click="showApprouver = false">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" @click="approuver">Confirmer</PrimaryButton>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="showRejeter" @close="showRejeter = false">
            <template #title>Refuser le paiement</template>
            <template #content>
                <InputLabel for="motif" value="Motif du refus *" />
                <textarea id="motif" v-model="formRejet.motif" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                <InputError :message="formRejet.errors.motif" class="mt-2" />
            </template>
            <template #footer>
                <SecondaryButton @click="showRejeter = false">Annuler</SecondaryButton>
                <DangerButton class="ms-3" :disabled="formRejet.processing" @click="rejeter">Refuser le paiement</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
