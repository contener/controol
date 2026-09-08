<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white shadow-sm hover:bg-blue-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none disabled:shadow-none transition-all duration-150">
        <slot />
    </button>
</template>
