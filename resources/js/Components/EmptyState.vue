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
    <div class="text-center py-12 px-4">
        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ titre }}</p>
        <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-sm mx-auto">{{ description }}</p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
