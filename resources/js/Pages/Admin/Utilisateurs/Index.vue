<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    utilisateurs: Object,
    filtres: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const statut = ref(props.filtres.statut ?? '');

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch(statut, appliquerFiltres);

function appliquerFiltres() {
    router.get(route('admin.utilisateurs.index'), {
        recherche: recherche.value,
        statut: statut.value,
    }, { preserveState: true, replace: true });
}

const basculer = (utilisateur) => {
    const action = utilisateur.est_actif ? 'désactiver' : 'réactiver';
    if (confirm(`Confirmer : ${action} "${utilisateur.name}" ?`)) {
        router.patch(route('admin.utilisateurs.basculer', utilisateur.id), {}, { preserveScroll: true });
    }
};

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR');
</script>

<template>
    <AppLayout title="Administration — Utilisateurs">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Gestion des utilisateurs</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <TextInput v-model="recherche" placeholder="Rechercher par nom ou email..." class="flex-1" />
                    <SelectInput v-model="statut" class="sm:w-48">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Boutiques</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Inscrit le</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="utilisateurs.data.length === 0">
                                <td colspan="7" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucun utilisateur trouvé.</td>
                            </tr>
                            <tr v-for="utilisateur in utilisateurs.data" :key="utilisateur.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ utilisateur.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ utilisateur.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ utilisateur.plan ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ utilisateur.boutiques_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="utilisateur.est_actif ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                                        {{ utilisateur.est_actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDate(utilisateur.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('admin.utilisateurs.show', utilisateur.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Voir</Link>
                                    <button class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-200" @click="basculer(utilisateur)">
                                        {{ utilisateur.est_actif ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="utilisateurs.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in utilisateurs.links"
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
