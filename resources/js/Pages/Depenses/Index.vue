<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    depenses: Object,
    categories: Array,
    filtres: Object,
});

const categorie = ref(props.filtres.categorie ?? '');
const debut = ref(props.filtres.debut ?? '');
const fin = ref(props.filtres.fin ?? '');

watch([categorie, debut, fin], () => {
    router.get(route('depenses.index'), { categorie: categorie.value, debut: debut.value, fin: fin.value }, {
        preserveState: true,
        replace: true,
    });
});

const supprimer = (depense) => {
    if (confirm('Supprimer cette dépense ?')) {
        router.delete(route('depenses.destroy', depense.id));
    }
};

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant);
</script>

<template>
    <AppLayout title="Dépenses">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Dépenses</h2>
                <Link :href="route('depenses.create')">
                    <PrimaryButton>Nouvelle dépense</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <SelectInput v-model="categorie" class="sm:w-56">
                        <option value="">Toutes les catégories</option>
                        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                    </SelectInput>
                    <TextInput v-model="debut" type="date" class="sm:w-48" />
                    <TextInput v-model="fin" type="date" class="sm:w-48" />
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Catégorie</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Description</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="depenses.data.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucune dépense enregistrée.</td>
                            </tr>
                            <tr v-for="depense in depenses.data" :key="depense.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ depense.date_depense }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">{{ depense.categorie }}</td>
                                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ depense.description }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(depense.montant) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('depenses.edit', depense.id)" class="text-blue-600 hover:text-blue-900 dark:hover:text-slate-200">Modifier</Link>
                                    <button class="text-red-600 hover:text-red-900 dark:hover:text-slate-200" @click="supprimer(depense)">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="depenses.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in depenses.links"
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
