<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FacturesSubNav from './Partials/FacturesSubNav.vue';
import FactureEditorShell from './Partials/FactureEditorShell.vue';

const props = defineProps({
    facture: Object,
    clients: Array,
    produits: Array,
    boutique: Object,
    modeles: Array,
});

const form = useForm({
    client_id: props.facture.client_id,
    date_emission: props.facture.date_emission,
    date_echeance: props.facture.date_echeance ?? '',
    remise: props.facture.remise,
    notes: props.facture.notes ?? '',
    garantie: props.facture.garantie ?? '',
    statut: props.facture.statut,
    modele_id: props.facture.modele_id,
    lignes: props.facture.lignes.map((ligne) => ({
        produit_id: ligne.produit_id,
        designation: ligne.designation,
        description: ligne.description ?? '',
        quantite: ligne.quantite,
        prix_unitaire: ligne.prix_unitaire,
        tva_taux: ligne.tva_taux,
        remise_ligne: ligne.remise_ligne,
    })),
});

const submit = () => {
    form.put(route('factures.update', props.facture.id));
};
</script>

<template>
    <AppLayout title="Modifier la facture">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Modifier la facture {{ facture.numero }}</h2>
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
                    :numero-previsualise="facture.numero"
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
