<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    parrains: Object,
    filtres: Object,
    statistiques: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const debut = ref(props.filtres.debut ?? '');
const fin = ref(props.filtres.fin ?? '');

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch([debut, fin], appliquerFiltres);

function appliquerFiltres() {
    router.get(route('admin.parrainage.index'), {
        recherche: recherche.value,
        debut: debut.value,
        fin: fin.value,
    }, { preserveState: true, replace: true });
}

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' FCFA';
</script>

<template>
    <AppLayout title="Administration — Parrainage">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🤝 Gestion du parrainage</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Parrains actifs</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.total_parrains_actifs }}</div>
                    </div>
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
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Commissions disponibles</div>
                        <div class="mt-1 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ formatMontant(statistiques.commissions_disponibles) }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Déjà payées</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatMontant(statistiques.commissions_deja_payees) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <TextInput v-model="recherche" placeholder="Nom ou email du parrain..." />
                    <TextInput v-model="debut" type="date" />
                    <TextInput v-model="fin" type="date" />
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Parrain</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Filleuls</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Solde disponible</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="parrains.data.length === 0">
                                <td colspan="4" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun parrain pour le moment.</td>
                            </tr>
                            <tr v-for="parrain in parrains.data" :key="parrain.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">
                                    {{ parrain.name }}
                                    <div class="text-xs text-slate-400 dark:text-slate-500">{{ parrain.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ parrain.filleuls_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-blue-600 dark:text-blue-400">{{ formatMontant(parrain.solde_disponible) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <Link :href="route('admin.parrainage.show', parrain.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Voir</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="parrains.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in parrains.links"
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
    </AppLayout>
</template>
