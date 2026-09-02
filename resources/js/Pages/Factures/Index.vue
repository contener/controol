<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import FacturesSubNav from './Partials/FacturesSubNav.vue';

const props = defineProps({
    factures: Object,
    clients: Array,
    filtres: Object,
    modeleLabels: Object,
});

const statut = ref(props.filtres.statut ?? '');
const clientId = ref(props.filtres.client_id ?? '');

watch([statut, clientId], () => {
    router.get(route('factures.index'), { statut: statut.value, client_id: clientId.value }, {
        preserveState: true,
        replace: true,
    });
});

const statutLabels = {
    brouillon: 'Brouillon',
    envoyee: 'Envoyée',
    payee: 'Payée',
    annulee: 'Annulée',
};

const statutClasses = {
    brouillon: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
    envoyee: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    payee: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    annulee: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(montant);

const dupliquer = (facture) => {
    router.post(route('factures.dupliquer', facture.id));
};
</script>

<template>
    <AppLayout title="Factures">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Factures</h2>
                <Link :href="route('factures.create')">
                    <PrimaryButton>Nouvelle facture</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <FacturesSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <SelectInput v-model="statut" class="sm:w-56">
                        <option value="">Tous les statuts</option>
                        <option value="brouillon">Brouillon</option>
                        <option value="envoyee">Envoyée</option>
                        <option value="payee">Payée</option>
                        <option value="annulee">Annulée</option>
                    </SelectInput>
                    <SelectInput v-model="clientId" class="sm:w-56">
                        <option value="">Tous les clients</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.nom }}</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Numéro</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Modèle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total TTC</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="factures.data.length === 0">
                                <td colspan="7" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucune facture trouvée.</td>
                            </tr>
                            <tr v-for="facture in factures.data" :key="facture.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100 cursor-pointer" @click="$inertia.visit(route('factures.show', facture.id))">{{ facture.numero }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ facture.client?.nom }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ facture.date_emission }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ modeleLabels[facture.modele_id] ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[facture.statut]">{{ statutLabels[facture.statut] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatMontant(facture.total_ttc) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('factures.show', facture.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Voir</Link>
                                    <Link v-if="facture.statut === 'brouillon'" :href="route('factures.edit', facture.id)" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-200">Modifier</Link>
                                    <button class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-200" @click="dupliquer(facture)">Dupliquer</button>
                                    <a :href="route('factures.pdf', facture.id)" target="_blank" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-200">Télécharger</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="factures.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in factures.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
