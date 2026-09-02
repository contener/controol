<script setup>
import { Link } from '@inertiajs/vue3';

const liens = [
    { route: 'factures.index', pattern: 'factures.index', label: 'Mes factures' },
    { route: 'factures.create', pattern: 'factures.create', label: 'Nouvelle facture' },
    { route: 'factures.modeles', pattern: 'factures.modeles', label: 'Modèles de facture' },
];
</script>

<template>
    <nav class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-700">
        <Link
            v-for="lien in liens"
            :key="lien.route"
            :href="route(lien.route)"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
            :class="route().current(lien.pattern)
                ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
        >
            {{ lien.label }}
        </Link>
    </nav>
</template>
