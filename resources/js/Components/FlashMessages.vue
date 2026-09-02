<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const success = computed(() => page.props.flash?.success);
const error = computed(() => page.props.flash?.error);
</script>

<template>
    <div v-if="success || error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div v-if="success" class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ success }}
        </div>
        <div v-if="error" class="rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
            {{ error }}
        </div>
    </div>
</template>
