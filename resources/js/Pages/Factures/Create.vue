<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FacturesSubNav from './Partials/FacturesSubNav.vue';
import FactureEditorShell from './Partials/FactureEditorShell.vue';

const props = defineProps({
    clients: Array,
    produits: Array,
    tauxTvaDefaut: Number,
    boutique: Object,
    modeles: Array,
    modeleInitial: {
        type: Number,
        default: 1,
    },
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    client_id: null,
    date_emission: today,
    date_echeance: '',
    remise: 0,
    notes: '',
    statut: 'brouillon',
    modele_id: props.modeleInitial,
    lignes: [
        {
            produit_id: null,
            designation: '',
            description: '',
            quantite: 1,
            prix_unitaire: 0,
            tva_taux: props.tauxTvaDefaut,
            remise_ligne: 0,
        },
    ],
});

const submit = () => {
    form.post(route('factures.store'));
};
</script>

<template>
    <AppLayout title="Nouvelle facture">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Nouvelle facture</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FacturesSubNav />

                <FactureEditorShell
                    :form="form"
                    :processing="form.processing"
                    :clients="clients"
                    :produits="produits"
                    :boutique="boutique"
                    :modeles="modeles"
                    :taux-tva-defaut="tauxTvaDefaut"
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
