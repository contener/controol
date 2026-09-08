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
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Administration — Marketplace</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-3">
                    <TextInput v-model="recherche" placeholder="Rechercher une boutique..." class="flex-1" />
                    <SelectInput v-model="statutMarketplace" class="sm:w-56">
                        <option value="">Toutes les boutiques</option>
                        <option value="eligibles">Éligibles (visibles)</option>
                        <option value="non_eligibles">Non éligibles</option>
                        <option value="desactivees">Désactivées par l'admin</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Boutique</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Propriétaire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Produits</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Marketplace</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="boutiques.data.length === 0">
                                <td colspan="6" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucune boutique.</td>
                            </tr>
                            <tr v-for="boutique in boutiques.data" :key="boutique.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ boutique.nom }}
                                    <a :href="route('public.boutique', boutique.slug)" target="_blank" class="ms-1 text-xs text-blue-500 dark:text-blue-400 hover:underline">voir</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ boutique.proprietaire.name }}
                                    <div class="text-xs text-slate-400 dark:text-slate-500">{{ boutique.proprietaire.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ boutique.plan ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-500 dark:text-slate-400">{{ boutique.produits_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="boutique.marketplace_disabled_by_admin" class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300">Désactivée (admin)</span>
                                    <span v-else-if="boutique.eligible" class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Éligible / visible</span>
                                    <span v-else class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">Non éligible</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <button class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300" @click="basculer(boutique)">
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
                        :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
