<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProduitForm from './Partials/ProduitForm.vue';

const form = useForm({
    type: 'produit',
    nom: '',
    description: '',
    reference: '',
    prix_achat: '',
    prix_vente: '',
    unite: 'pièce',
    tva_taux: '',
    gere_stock: false,
    quantite_stock: 0,
    seuil_alerte: '',
    actif: true,
    promotion_prix: '',
    categorie: '',
    photo: null,
});

const submit = () => {
    form.post(route('produits.store'), { forceFormData: true });
};
</script>

<template>
    <AppLayout title="Nouveau produit / service">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Nouveau produit / service</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <ProduitForm :form="form" :processing="form.processing" @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
