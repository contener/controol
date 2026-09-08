<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        class="border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm transition-colors duration-150"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
