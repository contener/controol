<script setup>
defineProps({
    couleur: {
        type: String,
        default: 'slate', // slate | blue | green | red | yellow
    },
});

const classesParCouleur = {
    slate: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
    blue: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
    green: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
    red: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
    yellow: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300',
};
</script>

<template>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="classesParCouleur[couleur] ?? classesParCouleur.slate">
        <slot />
    </span>
</template>
