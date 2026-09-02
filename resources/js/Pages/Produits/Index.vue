<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    produits: Object,
    filtres: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const type = ref(props.filtres.type ?? '');

const appliquerFiltres = () => {
    router.get(route('produits.index'), { recherche: recherche.value, type: type.value }, {
        preserveState: true,
        replace: true,
    });
};

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch(type, appliquerFiltres);

const supprimer = (produit) => {
    if (confirm(`Supprimer "${produit.nom}" ?`)) {
        router.delete(route('produits.destroy', produit.id));
    }
};

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(montant);
</script>

<template>
    <AppLayout title="Produits & Services">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Produits &amp; Services</h2>
                <Link :href="route('produits.create')">
                    <PrimaryButton>Nouveau produit / service</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <TextInput v-model="recherche" placeholder="Rechercher par nom ou référence..." class="flex-1" />
                    <SelectInput v-model="type" class="sm:w-48">
                        <option value="">Tous les types</option>
                        <option value="produit">Produit</option>
                        <option value="service">Service</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Référence</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Prix de vente</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stock</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="produits.data.length === 0">
                                <td colspan="6" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucun produit ou service trouvé.</td>
                            </tr>
                            <tr v-for="produit in produits.data" :key="produit.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ produit.nom }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="produit.type === 'produit' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-300'">
                                        {{ produit.type === 'produit' ? 'Produit' : 'Service' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ produit.reference ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatMontant(produit.prix_vente) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span v-if="produit.gere_stock" :class="produit.seuil_alerte !== null && produit.quantite_stock <= produit.seuil_alerte ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-900 dark:text-gray-100'">
                                        {{ produit.quantite_stock }}
                                    </span>
                                    <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('produits.edit', produit.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">Modifier</Link>
                                    <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="supprimer(produit)">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="produits.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in produits.links"
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
