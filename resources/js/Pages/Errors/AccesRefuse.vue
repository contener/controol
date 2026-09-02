<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    message: {
        type: String,
        default: "Vous n'avez pas l'autorisation d'accéder à cette page.",
    },
});

const DELAI_SECONDES = 5;
const secondesRestantes = ref(DELAI_SECONDES);
let intervalId = null;

const allerAAccueil = () => {
    router.visit('/');
};

onMounted(() => {
    intervalId = setInterval(() => {
        secondesRestantes.value -= 1;
        if (secondesRestantes.value <= 0) {
            clearInterval(intervalId);
            allerAAccueil();
        }
    }, 1000);
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

<template>
    <Head title="Accès refusé" />

    <div class="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 px-4">
        <div class="max-w-md w-full bg-white dark:bg-gray-800 shadow-sm rounded-lg p-8 text-center">
            <div class="text-5xl mb-4">🔒</div>
            <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Accès refusé</h1>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                {{ message }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Cette section est réservée au Super Administrateur.
            </p>

            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
                Redirection vers l'accueil dans {{ secondesRestantes }} seconde{{ secondesRestantes > 1 ? 's' : '' }}...
            </p>

            <PrimaryButton class="mt-6 justify-center w-full" @click="allerAAccueil">
                Retour à l'accueil
            </PrimaryButton>
        </div>
    </div>
</template>
