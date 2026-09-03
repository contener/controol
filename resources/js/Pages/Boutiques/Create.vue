<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BoutiqueForm from './Partials/BoutiqueForm.vue';

defineProps({
    peutCreer: Boolean,
});

const form = useForm({
    nom: '',
    description: '',
    categorie: '',
    adresse: '',
    ville: '',
    pays: '',
    telephone: '',
    whatsapp: '',
    email: '',
    devise: 'XAF',
    taux_tva_defaut: 19.25,
    nui: '',
    note_pied_facture: '',
    facebook_url: '',
    instagram_url: '',
    telegram_url: '',
    logo: null,
    banniere: null,
});

const submit = () => {
    form.post(route('boutiques.store'), { forceFormData: true });
};
</script>

<template>
    <AppLayout title="Nouvelle boutique">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Nouvelle boutique</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div v-if="!peutCreer" class="mb-4 rounded-md bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 px-4 py-3 text-sm text-yellow-800 dark:text-yellow-300">
                    Vous avez atteint la limite de boutiques de votre plan actuel.
                    <a :href="route('abonnement.index')" class="underline font-medium">Passez à un plan supérieur</a> pour en créer davantage.
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <BoutiqueForm :form="form" :processing="form.processing" @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
