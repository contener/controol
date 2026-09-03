<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BoutiqueForm from './Partials/BoutiqueForm.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    boutique: Object,
    planAutoriseMarketplace: Boolean,
});

const form = useForm({
    _method: 'put',
    nom: props.boutique.nom,
    description: props.boutique.description,
    categorie: props.boutique.categorie,
    adresse: props.boutique.adresse,
    ville: props.boutique.ville,
    pays: props.boutique.pays,
    telephone: props.boutique.telephone,
    whatsapp: props.boutique.whatsapp,
    email: props.boutique.email,
    devise: props.boutique.devise,
    taux_tva_defaut: props.boutique.taux_tva_defaut,
    nui: props.boutique.nui,
    note_pied_facture: props.boutique.note_pied_facture,
    facebook_url: props.boutique.facebook_url,
    instagram_url: props.boutique.instagram_url,
    telegram_url: props.boutique.telegram_url,
    logo: null,
    banniere: null,
});

const submit = () => {
    form.post(route('boutiques.update', props.boutique.id), { forceFormData: true });
};

const formMarketplace = useForm({
    marketplace_visible: props.boutique.marketplace_visible,
});

const toggleMarketplace = () => {
    formMarketplace.marketplace_visible = !formMarketplace.marketplace_visible;
    formMarketplace.patch(route('boutiques.marketplace', props.boutique.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Modifier la boutique">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Modifier la boutique</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <BoutiqueForm :form="form" :processing="form.processing" :boutique="boutique" @submit="submit" />
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Marketplace</h3>

                    <div v-if="planAutoriseMarketplace" class="mt-3">
                        <div v-if="formMarketplace.marketplace_visible" class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400">
                                ✓ Publiée sur la Marketplace
                            </span>
                            <button type="button" class="text-xs text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400" @click="toggleMarketplace">
                                Retirer de la Marketplace
                            </button>
                        </div>
                        <PrimaryButton v-else @click="toggleMarketplace">
                            🌐 Publier sur la Marketplace
                        </PrimaryButton>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Visible par tous les visiteurs de la Marketplace, en plus de votre lien public direct.</p>
                    </div>

                    <div v-else class="mt-3 rounded-md bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 px-4 py-3 text-sm text-yellow-800 dark:text-yellow-300">
                        <p class="font-medium">Marketplace indisponible</p>
                        <p class="mt-1">Votre abonnement actuel ne permet pas d'afficher votre boutique dans la Marketplace.</p>
                        <p class="mt-1">Passez au plan Basique ou Pro pour accéder à cette fonctionnalité.</p>
                        <Link :href="route('abonnement.index')" class="mt-3 inline-block">
                            <PrimaryButton>Mettre à niveau</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Partage &amp; publication sociale</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Gérez vos groupes de diffusion et vos campagnes de publication.</p>
                    </div>
                    <Link :href="route('partage-social.index')" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300">
                        Ouvrir →
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
