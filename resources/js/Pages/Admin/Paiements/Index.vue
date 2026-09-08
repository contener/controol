<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    paiements: Object,
    plans: Array,
    filtres: Object,
});

const statut = ref(props.filtres.statut ?? '');
const moyenPaiement = ref(props.filtres.moyen_paiement ?? '');
const planId = ref(props.filtres.plan_id ?? '');
const recherche = ref(props.filtres.recherche ?? '');
const debut = ref(props.filtres.debut ?? '');
const fin = ref(props.filtres.fin ?? '');

let timeoutId = null;
watch([statut, moyenPaiement, planId, debut, fin], appliquerFiltres);
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});

function appliquerFiltres() {
    router.get(route('admin.paiements.index'), {
        statut: statut.value,
        moyen_paiement: moyenPaiement.value,
        plan_id: planId.value,
        recherche: recherche.value,
        debut: debut.value,
        fin: fin.value,
    }, { preserveState: true, replace: true });
}

const formatMontant = (montant, devise) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + devise;

const statutLabels = { en_attente: 'En attente', approuve: 'Approuvé', rejete: 'Rejeté' };
const statutClasses = {
    en_attente: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    approuve: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    rejete: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};

const paiementAApprouver = ref(null);
const confirmerApprobation = (paiement) => {
    paiementAApprouver.value = paiement;
};
const validerApprobation = () => {
    router.post(route('admin.paiements.approuver', paiementAApprouver.value.id), {}, {
        onFinish: () => { paiementAApprouver.value = null; },
    });
};

const paiementARejeter = ref(null);
const formRejet = useForm({ motif: '' });
const ouvrirRejet = (paiement) => {
    formRejet.reset();
    formRejet.clearErrors();
    paiementARejeter.value = paiement;
};
const validerRejet = () => {
    formRejet.post(route('admin.paiements.rejeter', paiementARejeter.value.id), {
        onSuccess: () => { paiementARejeter.value = null; },
    });
};
</script>

<template>
    <AppLayout title="Administration — Paiements">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Administration — Paiements d'abonnement</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <TextInput v-model="recherche" placeholder="Nom ou email..." />
                    <SelectInput v-model="statut">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente">En attente</option>
                        <option value="approuve">Approuvés</option>
                        <option value="rejete">Refusés</option>
                    </SelectInput>
                    <SelectInput v-model="planId">
                        <option value="">Tous les plans</option>
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.nom }}</option>
                    </SelectInput>
                    <TextInput v-model="moyenPaiement" placeholder="Moyen de paiement" />
                    <TextInput v-model="debut" type="date" />
                    <TextInput v-model="fin" type="date" />
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Utilisateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Moyen</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Créé le</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="paiements.data.length === 0">
                                <td colspan="7" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun paiement.</td>
                            </tr>
                            <tr v-for="paiement in paiements.data" :key="paiement.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ paiement.user.name }}
                                    <div class="text-xs text-slate-400 dark:text-slate-500">{{ paiement.user.email }}</div>
                                    <div v-if="paiement.user.telephone" class="text-xs text-slate-400 dark:text-slate-500">{{ paiement.user.telephone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ paiement.abonnement?.plan?.nom ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(paiement.montant, paiement.devise) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ paiement.moyen_paiement }}
                                    <div v-if="paiement.reference_transaction" class="text-xs text-slate-400 dark:text-slate-500">Réf. {{ paiement.reference_transaction }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ new Date(paiement.created_at).toLocaleString('fr-FR') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[paiement.statut]">{{ statutLabels[paiement.statut] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                                    <Link :href="route('admin.paiements.show', paiement.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Voir</Link>
                                    <template v-if="paiement.statut === 'en_attente'">
                                        <button class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300" @click="confirmerApprobation(paiement)">Approuver</button>
                                        <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="ouvrirRejet(paiement)">Refuser</button>
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="paiements.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in paiements.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>

        <ConfirmationModal :show="!!paiementAApprouver" @close="paiementAApprouver = null">
            <template #title>Confirmer la validation ?</template>
            <template #content>
                Vous êtes sur le point d'activer le plan
                <strong>{{ paiementAApprouver?.abonnement?.plan?.nom }}</strong>
                pour <strong>{{ paiementAApprouver?.user?.name }}</strong>.
            </template>
            <template #footer>
                <SecondaryButton @click="paiementAApprouver = null">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" @click="validerApprobation">Confirmer</PrimaryButton>
            </template>
        </ConfirmationModal>

        <ConfirmationModal :show="!!paiementARejeter" @close="paiementARejeter = null">
            <template #title>Refuser le paiement</template>
            <template #content>
                <InputLabel for="motif" value="Motif du refus *" />
                <textarea id="motif" v-model="formRejet.motif" rows="3" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" placeholder="Référence de paiement incorrecte..." />
                <InputError :message="formRejet.errors.motif" class="mt-2" />
            </template>
            <template #footer>
                <SecondaryButton @click="paiementARejeter = null">Annuler</SecondaryButton>
                <DangerButton class="ms-3" :disabled="formRejet.processing" @click="validerRejet">Refuser le paiement</DangerButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
