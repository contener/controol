<script setup>
import { computed } from 'vue';
import { registreModeles } from '@/Pages/Factures/Modeles/registry.js';

const props = defineProps({
    modeleId: {
        type: Number,
        required: true,
    },
    apercu: {
        type: Object,
        required: true,
    },
});

const composant = computed(() => registreModeles[props.modeleId] ?? registreModeles[1]);
</script>

<template>
    <component :is="composant" :apercu="apercu" />
</template>
