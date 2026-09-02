<script setup>
defineProps({
    modelValue: [String, Number],
});

defineEmits(['update:modelValue']);
</script>

<template>
    <select
        class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>
