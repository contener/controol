<script setup>
defineProps({
    modelValue: [String, Number],
});

defineEmits(['update:modelValue']);
</script>

<template>
    <select
        class="border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm transition-colors duration-150"
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>
