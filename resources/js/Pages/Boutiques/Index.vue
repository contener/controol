<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    boutiques: Array,
    planAutoriseMarketplace: Boolean,
});

const supprimer = (boutique) => {
    if (confirm(`Supprimer la boutique "${boutique.nom}" ? Toutes ses données (clients, produits, factures...) seront supprimées définitivement.`)) {
        router.delete(route('boutiques.destroy', boutique.id));
    }
};

const changer = (boutique) => {
    router.post(route('boutiques.switch', boutique.id));
};

const publierMarketplace = (boutique, visible) => {
    router.patch(route('boutiques.marketplace', boutique.id), { marketplace_visible: visible }, { preserveScroll: true });
};

const aUneBoutiqueNonPubliee = computed(() => props.planAutoriseMarketplace && props.boutiques.some((b) => !b.marketplace_visible));
</script>

<template>
    <AppLayout title="Mes boutiques">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Mes boutiques</h2>
                <Link :href="route('boutiques.create')">
                    <PrimaryButton>Nouvelle boutique</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="aUneBoutiqueNonPubliee" class="rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 text-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">🎉 Votre abonnement est actif !</p>
                        <p class="text-sm text-blue-100 mt-0.5">Vous pouvez publier vos boutiques sur la Marketplace pour toucher plus de clients.</p>
                    </div>
                </div>

                <div v-else-if="!planAutoriseMarketplace" class="rounded-lg bg-slate-100 dark:bg-slate-800 px-6 py-4 text-sm text-slate-600 dark:text-slate-300 flex flex-wrap items-center justify-between gap-3">
                    <span>🌐 La Marketplace permet de présenter votre boutique à davantage de clients. Disponible avec un abonnement payant.</span>
                    <Link :href="route('abonnement.index')">
                        <SecondaryButton>Voir les forfaits</SecondaryButton>
                    </Link>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="boutique in boutiques" :key="boutique.id" class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ boutique.nom }}</h3>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="boutique.statut === 'active' ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'">
                                    {{ boutique.statut === 'active' ? 'Active' : 'Suspendue' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-sm text-slate-500 dark:text-slate-400">
                            <div>{{ boutique.clients_count }} client(s)</div>
                            <div>{{ boutique.produits_count }} produit(s)</div>
                            <div>{{ boutique.factures_count }} facture(s)</div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                            <template v-if="planAutoriseMarketplace">
                                <div v-if="boutique.marketplace_visible" class="flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400">
                                        ✓ Publiée sur la Marketplace
                                    </span>
                                    <button class="text-xs text-slate-500 dark:text-slate-400 hover:text-red-600 dark:hover:text-red-400" @click="publierMarketplace(boutique, false)">
                                        Retirer de la Marketplace
                                    </button>
                                </div>
                                <PrimaryButton v-else class="w-full justify-center" @click="publierMarketplace(boutique, true)">
                                    🌐 Publier sur la Marketplace
                                </PrimaryButton>
                            </template>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                            <button class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300" @click="changer(boutique)">Basculer sur cette boutique</button>
                            <Link :href="route('boutiques.edit', boutique.id)" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-200">Modifier</Link>
                            <a :href="route('public.boutique', boutique.slug)" target="_blank" class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-200">Voir la page publique</a>
                            <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="supprimer(boutique)">Supprimer</button>
                        </div>
                    </div>
                </div>

                <div v-if="boutiques.length === 0" class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-10 text-center text-slate-500 dark:text-slate-400">
                    Vous n'avez pas encore de boutique.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
