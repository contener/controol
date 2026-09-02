<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    produits: Object,
    filtres: Object,
});

const ruptureUniquement = ref(!!props.filtres.rupture);
const toggleRupture = () => {
    router.get(route('stock.index'), { rupture: ruptureUniquement.value ? 1 : undefined }, {
        preserveState: true,
        replace: true,
    });
};

const produitCible = ref(null);
const showModal = ref(false);

const form = useForm({
    produit_id: null,
    type: 'entree',
    quantite: 1,
    motif: '',
});

const ouvrirModal = (produit) => {
    produitCible.value = produit;
    form.reset();
    form.produit_id = produit.id;
    showModal.value = true;
};

const fermerModal = () => {
    showModal.value = false;
    form.clearErrors();
};

const submit = () => {
    form.post(route('stock.store'), {
        onSuccess: () => fermerModal(),
    });
};
</script>

<template>
    <AppLayout title="Stock">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Gestion des stocks</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4">
                    <label class="flex items-center gap-2">
                        <Checkbox v-model:checked="ruptureUniquement" @change="toggleRupture" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Afficher uniquement les produits en rupture de stock</span>
                    </label>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Référence</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantité en stock</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Seuil d'alerte</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="produits.data.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucun produit avec suivi de stock.</td>
                            </tr>
                            <tr v-for="produit in produits.data" :key="produit.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ produit.nom }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ produit.reference ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="produit.seuil_alerte !== null && produit.quantite_stock <= produit.seuil_alerte ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-900 dark:text-gray-100'">
                                    {{ produit.quantite_stock }} {{ produit.unite }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">{{ produit.seuil_alerte ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" @click="ouvrirModal(produit)">Mouvement</button>
                                    <Link :href="route('stock.mouvements', produit.id)" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-200">Historique</Link>
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

        <Modal :show="showModal" @close="fermerModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mouvement de stock — {{ produitCible?.nom }}</h3>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <InputLabel for="mvt_type" value="Type de mouvement *" />
                        <SelectInput id="mvt_type" v-model="form.type" class="mt-1 block w-full">
                            <option value="entree">Entrée (réapprovisionnement)</option>
                            <option value="sortie">Sortie</option>
                            <option value="ajustement">Ajustement (fixer la quantité exacte)</option>
                        </SelectInput>
                        <InputError :message="form.errors.type" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="mvt_quantite" :value="form.type === 'ajustement' ? 'Nouvelle quantité *' : 'Quantité *'" />
                        <TextInput id="mvt_quantite" v-model="form.quantite" type="number" min="1" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.quantite" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="mvt_motif" value="Motif" />
                        <TextInput id="mvt_motif" v-model="form.motif" type="text" class="mt-1 block w-full" placeholder="Réapprovisionnement fournisseur, casse, inventaire..." />
                        <InputError :message="form.errors.motif" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <SecondaryButton type="button" @click="fermerModal">Annuler</SecondaryButton>
                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
