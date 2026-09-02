<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TemplatePickerCard from '@/Components/TemplatePickerCard.vue';
import FacturesSubNav from '../Partials/FacturesSubNav.vue';
import { exempleApercu } from './exempleApercu.js';

defineProps({
    modeles: Array,
});

const choisir = (id) => {
    router.visit(route('factures.create', { modele: id }));
};
</script>

<template>
    <AppLayout title="Modèles de facture">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Modèles de facture</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <FacturesSubNav />

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Choisissez un modèle pour démarrer une nouvelle facture. Les modèles verrouillés sont réservés aux plans Basique et Pro.
                </p>

                <TemplatePickerCard :modeles="modeles" :apercu-exemple="exempleApercu" @select="choisir" />
            </div>
        </div>
    </AppLayout>
</template>
