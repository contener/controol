<script setup>
defineProps({
    titre: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ titre }}</h2>
            <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex items-center gap-2 flex-wrap">
            <slot />
        </div>
    </div>
</template>
