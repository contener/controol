<script setup>
import { Link } from '@inertiajs/vue3';
import InvoicePreview from '@/Components/InvoicePreview.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    modeles: {
        type: Array,
        required: true,
    },
    selectionne: {
        type: Number,
        default: null,
    },
    apercuExemple: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['select']);
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
            v-for="modele in modeles"
            :key="modele.id"
            class="relative bg-white dark:bg-gray-800 rounded-lg shadow-sm border-2 overflow-hidden transition"
            :class="selectionne === modele.id ? 'border-indigo-500' : 'border-transparent'"
        >
            <div class="h-40 overflow-hidden relative bg-gray-100 dark:bg-gray-900">
                <div class="origin-top-left scale-[0.27] w-[370%] h-[370%] pointer-events-none">
                    <InvoicePreview :modele-id="modele.id" :apercu="apercuExemple" />
                </div>

                <div v-if="!modele.autorise" class="absolute inset-0 bg-gray-900/70 flex flex-col items-center justify-center text-center px-4 gap-2">
                    <span class="text-2xl">🔒</span>
                    <p class="text-white text-xs">Disponible avec le plan Basique ou Pro</p>
                    <Link :href="route('abonnement.index')">
                        <PrimaryButton class="text-xs py-1.5">Passer au plan payant</PrimaryButton>
                    </Link>
                </div>
            </div>

            <div class="p-3 flex items-center justify-between gap-2">
                <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ modele.label }}</div>
                    <span class="text-xs" :class="modele.gratuit ? 'text-green-600 dark:text-green-400' : 'text-indigo-600 dark:text-indigo-400'">
                        {{ modele.gratuit ? 'Gratuit' : 'Basique / Pro' }}
                    </span>
                </div>
                <button
                    v-if="modele.autorise"
                    type="button"
                    class="text-xs font-medium px-2.5 py-1.5 rounded-md"
                    :class="selectionne === modele.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600'"
                    @click="emit('select', modele.id)"
                >
                    {{ selectionne === modele.id ? '✓ Sélectionné' : 'Choisir' }}
                </button>
            </div>
        </div>
    </div>
</template>
