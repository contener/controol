<script setup>
import { Link } from '@inertiajs/vue3';

const liens = [
    { route: 'factures.index', pattern: 'factures.index', label: 'Mes factures' },
    { route: 'factures.create', pattern: 'factures.create', label: 'Nouvelle facture' },
    { route: 'factures.modeles', pattern: 'factures.modeles', label: 'Modèles de facture' },
];
</script>

<template>
    <nav class="flex gap-1 mb-6 border-b border-slate-200 dark:border-slate-700">
        <Link
            v-for="lien in liens"
            :key="lien.route"
            :href="route(lien.route)"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
            :class="route().current(lien.pattern)
                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'"
        >
            {{ lien.label }}
        </Link>
    </nav>
</template>
