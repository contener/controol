<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    produit: Object,
    mouvements: Object,
});

const typeLabels = {
    entree: 'Entrée',
    sortie: 'Sortie',
    ajustement: 'Ajustement',
};

const typeClasses = {
    entree: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    sortie: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
    ajustement: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
};
</script>

<template>
    <AppLayout :title="`Historique — ${produit.nom}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Historique de stock — {{ produit.nom }}</h2>
                <Link :href="route('stock.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Retour au stock</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantité</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avant → Après</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Motif</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Utilisateur</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="mouvements.data.length === 0">
                                <td colspan="6" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucun mouvement enregistré.</td>
                            </tr>
                            <tr v-for="mvt in mouvements.data" :key="mvt.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ new Date(mvt.created_at).toLocaleString('fr-FR') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="typeClasses[mvt.type]">{{ typeLabels[mvt.type] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ mvt.quantite }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">{{ mvt.quantite_avant }} → {{ mvt.quantite_apres }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ mvt.motif ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ mvt.user?.name ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="mouvements.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in mouvements.links"
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
