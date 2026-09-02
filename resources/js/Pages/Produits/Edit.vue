<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProduitForm from './Partials/ProduitForm.vue';

const props = defineProps({
    produit: Object,
});

const form = useForm({
    _method: 'put',
    type: props.produit.type,
    nom: props.produit.nom,
    description: props.produit.description,
    reference: props.produit.reference,
    prix_achat: props.produit.prix_achat,
    prix_vente: props.produit.prix_vente,
    unite: props.produit.unite,
    tva_taux: props.produit.tva_taux,
    gere_stock: props.produit.gere_stock,
    quantite_stock: props.produit.quantite_stock,
    seuil_alerte: props.produit.seuil_alerte,
    actif: props.produit.actif,
    promotion_prix: props.produit.promotion_prix,
    categorie: props.produit.categorie,
    photo: null,
});

const submit = () => {
    form.post(route('produits.update', props.produit.id), { forceFormData: true });
};
</script>

<template>
    <AppLayout title="Modifier le produit / service">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Modifier le produit / service</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <ProduitForm :form="form" :processing="form.processing" :produit="produit" modification @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
