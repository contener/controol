<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from './Partials/AdminSubNav.vue';

const props = defineProps({
    stats: Object,
});

const formatNombre = (n) => new Intl.NumberFormat('fr-FR').format(n ?? 0);

const tuiles = [
    { label: 'Utilisateurs', cle: 'utilisateurs', icone: '👤' },
    { label: 'Boutiques', cle: 'boutiques', icone: '🏪' },
    { label: 'Boutiques Marketplace', cle: 'boutiques_marketplace', icone: '🛍️' },
    { label: 'Administrateurs actifs', cle: 'administrateurs_actifs', icone: '🛡️' },
    { label: 'Paiements en attente', cle: 'paiements_en_attente', icone: '⏳' },
    { label: 'Paiements approuvés', cle: 'paiements_approuves', icone: '✅' },
    { label: 'Paiements refusés', cle: 'paiements_rejetes', icone: '❌' },
];
</script>

<template>
    <AppLayout title="Super Administration">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Super Administration</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div v-for="tuile in tuiles" :key="tuile.cle" class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ tuile.label }}</span>
                            <span class="text-xl">{{ tuile.icone }}</span>
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatNombre(stats[tuile.cle]) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Abonnés par plan</h3>
                    <div class="flex flex-wrap gap-6">
                        <div v-for="(total, plan) in stats.abonnes_par_plan" :key="plan">
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">{{ plan }}</div>
                            <div class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatNombre(total) }}</div>
                        </div>
                        <div v-if="Object.keys(stats.abonnes_par_plan ?? {}).length === 0" class="text-sm text-slate-400 dark:text-slate-500">
                            Aucun abonnement actif pour le moment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
