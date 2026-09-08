<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Contenu from './Partials/Contenu.vue';

defineProps({
    boutiques: Object,
    categories: Array,
    villes: Array,
    promotions: Array,
    filtres: Object,
    meta: Object,
    planAutoriseMarketplace: Boolean,
});

const page = usePage();
const utilisateur = computed(() => page.props.auth?.user ?? null);
</script>

<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description">
        <meta property="og:title" :content="meta.title">
        <meta property="og:description" :content="meta.description">
        <meta property="og:url" :content="meta.url">
        <meta property="og:type" content="website">
    </Head>

    <AppLayout v-if="utilisateur" title="Marketplace">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 leading-tight">Marketplace</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="!planAutoriseMarketplace" class="rounded-lg bg-white dark:bg-slate-800 shadow-sm p-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-slate-900 dark:text-slate-100">Présentez votre boutique à davantage de clients</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Cette fonctionnalité est disponible avec un abonnement payant.</p>
                    </div>
                    <Link :href="route('abonnement.index')">
                        <PrimaryButton>Voir les forfaits</PrimaryButton>
                    </Link>
                </div>

                <Contenu
                    :boutiques="boutiques"
                    :categories="categories"
                    :villes="villes"
                    :promotions="promotions"
                    :filtres="filtres"
                    :marketplace-url="meta.url"
                />
            </div>
        </div>
    </AppLayout>

    <div v-else class="min-h-screen bg-slate-50">
        <header class="bg-white border-b border-slate-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationMark class="h-7 w-auto" />
                    <span class="font-semibold text-slate-900">Controol</span>
                </Link>
                <div class="flex items-center gap-3">
                    <Link :href="route('login')" class="text-sm font-medium text-slate-600 hover:text-slate-900">Se connecter</Link>
                    <Link :href="route('register')">
                        <PrimaryButton>Créer un compte</PrimaryButton>
                    </Link>
                </div>
            </div>
        </header>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <Contenu
                :boutiques="boutiques"
                :categories="categories"
                :villes="villes"
                :promotions="promotions"
                :filtres="filtres"
                :marketplace-url="meta.url"
            />

            <div class="mt-12 bg-gradient-to-br from-blue-600 to-purple-700 rounded-2xl p-8 sm:p-12 text-center text-white">
                <h2 class="text-2xl font-bold">Vous souhaitez vous aussi vendre vos produits en ligne ?</h2>
                <p class="mt-3 text-blue-100 max-w-xl mx-auto">
                    Créez votre propre boutique gratuitement et commencez à présenter vos produits à vos clients dès aujourd'hui.
                </p>
                <Link :href="route('register')" class="mt-6 inline-flex items-center justify-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                    Créer ma boutique gratuitement
                </Link>
            </div>
        </div>
    </div>
</template>
