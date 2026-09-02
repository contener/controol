<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    boutiques: Object,
    filtres: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const statutMarketplace = ref(props.filtres.statut_marketplace ?? '');

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch(statutMarketplace, appliquerFiltres);

function appliquerFiltres() {
    router.get(route('admin.marketplace.index'), {
        recherche: recherche.value,
        statut_marketplace: statutMarketplace.value,
    }, { preserveState: true, replace: true });
}

const basculer = (boutique) => {
    const action = boutique.marketplace_disabled_by_admin ? 'réactiver' : 'désactiver';
    if (confirm(`Confirmer : ${action} "${boutique.nom}" dans la Marketplace ?`)) {
        router.patch(route('admin.marketplace.basculer', boutique.id));
    }
};
</script>

<template>
    <AppLayout title="Administration — Marketplace">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Administration — Marketplace</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-3">
                    <TextInput v-model="recherche" placeholder="Rechercher une boutique..." class="flex-1" />
                    <SelectInput v-model="statutMarketplace" class="sm:w-56">
                        <option value="">Toutes les boutiques</option>
                        <option value="eligibles">Éligibles (visibles)</option>
                        <option value="non_eligibles">Non éligibles</option>
                        <option value="desactivees">Désactivées par l'admin</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Boutique</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Propriétaire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produits</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Marketplace</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="boutiques.data.length === 0">
                                <td colspan="6" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucune boutique.</td>
                            </tr>
                            <tr v-for="boutique in boutiques.data" :key="boutique.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ boutique.nom }}
                                    <a :href="route('public.boutique', boutique.slug)" target="_blank" class="ms-1 text-xs text-indigo-500 dark:text-indigo-400 hover:underline">voir</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ boutique.proprietaire.name }}
                                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ boutique.proprietaire.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ boutique.plan ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">{{ boutique.produits_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="boutique.marketplace_disabled_by_admin" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Désactivée (admin)</span>
                                    <span v-else-if="boutique.eligible" class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Éligible / visible</span>
                                    <span v-else class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Non éligible</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" @click="basculer(boutique)">
                                        {{ boutique.marketplace_disabled_by_admin ? 'Réactiver' : 'Désactiver' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="boutiques.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in boutiques.links"
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
