<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    administrateurs: Object,
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
    router.get(route('admin.administrateurs.index'), {
        recherche: recherche.value,
        statut: statut.value,
    }, { preserveState: true, replace: true });
}

const basculer = (administrateur) => {
    const action = administrateur.est_actif ? 'désactiver' : 'réactiver';
    if (confirm(`Confirmer : ${action} "${administrateur.name}" ?`)) {
        router.patch(route('admin.administrateurs.basculer', administrateur.id), {}, { preserveScroll: true });
    }
};

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR');
</script>

<template>
    <AppLayout title="Administration — Administrateurs">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Gestion des administrateurs</h2>
                <Link :href="route('admin.administrateurs.create')">
                    <PrimaryButton>+ Créer un administrateur</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <TextInput v-model="recherche" placeholder="Rechercher par nom ou email..." class="flex-1" />
                    <SelectInput v-model="statut" class="sm:w-48">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Rôle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Permissions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Créé le</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="administrateurs.data.length === 0">
                                <td colspan="7" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun administrateur trouvé.</td>
                            </tr>
                            <tr v-for="administrateur in administrateurs.data" :key="administrateur.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ administrateur.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ administrateur.email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ administrateur.admin_role_label ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ administrateur.admin_permissions_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="administrateur.est_actif ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'">
                                        {{ administrateur.est_actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(administrateur.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('admin.administrateurs.edit', administrateur.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Modifier</Link>
                                    <button class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-200" @click="basculer(administrateur)">
                                        {{ administrateur.est_actif ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="administrateurs.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in administrateurs.links"
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
