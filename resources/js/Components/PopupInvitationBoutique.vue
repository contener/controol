<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    boutique: { type: Object, required: true }, // { nom, slug }
    connecte: { type: Boolean, default: false },
});

const DELAI_INITIAL_MS = 5000;
const DELAI_REAPPARITION_MS = 5 * 60 * 1000;

const visible = ref(false);
let timer = null;

const cleStorage = `ctrl_popup_invitation_${props.boutique.slug}`;

const lireFermetureA = () => {
    try {
        const valeur = sessionStorage.getItem(cleStorage);
        return valeur ? parseInt(valeur, 10) : null;
    } catch (e) {
        return null;
    }
};

const ecrireFermetureA = (timestamp) => {
    try {
        sessionStorage.setItem(cleStorage, String(timestamp));
    } catch (e) {
        // Navigation privée ou stockage indisponible : le popup réapparaîtra simplement
        // au délai initial à chaque rechargement, sans casser la page.
    }
};

const planifier = (delaiMs) => {
    clearTimeout(timer);
    timer = setTimeout(() => { visible.value = true; }, delaiMs);
};

const fermer = () => {
    visible.value = false;
    ecrireFermetureA(Date.now());
    planifier(DELAI_REAPPARITION_MS);
};

onMounted(() => {
    const fermetureA = lireFermetureA();

    if (fermetureA === null) {
        planifier(DELAI_INITIAL_MS);
    } else {
        const ecoule = Date.now() - fermetureA;
        planifier(Math.max(0, DELAI_REAPPARITION_MS - ecoule));
    }
});

onUnmounted(() => clearTimeout(timer));

const lien = (suivre) => (props.connecte
    ? route('boutiques.create', { boutique: props.boutique.slug, suivre: suivre ? 1 : 0 })
    : route('register', { boutique: props.boutique.slug, suivre: suivre ? 1 : 0 }));

const texteCTAPrincipal = computed(() => (props.connecte
    ? `Créer ma boutique et suivre ${props.boutique.nom}`
    : `Créer mon compte et suivre ${props.boutique.nom}`));

const texteCTASecondaire = computed(() => (props.connecte
    ? 'Créer ma boutique sans suivre'
    : 'Créer un compte sans suivre'));
</script>

<template>
    <div v-if="visible" class="fixed inset-x-0 bottom-0 sm:inset-x-auto sm:bottom-6 sm:right-6 z-50 px-4 pb-4 sm:p-0 sm:max-w-sm">
        <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-700 p-5">
            <button
                type="button"
                class="absolute top-3 right-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                aria-label="Fermer"
                @click="fermer"
            >
                ✕
            </button>

            <div class="text-2xl">🚀</div>
            <h3 class="mt-2 pr-6 font-bold text-slate-900 dark:text-slate-100">Vous aimez {{ boutique.nom }} ?</h3>
            <ul class="mt-3 space-y-1.5 text-sm text-slate-600 dark:text-slate-300">
                <li>✅ Suivez cette boutique et recevez ses nouveautés</li>
                <li>✅ Créez votre propre boutique gratuitement</li>
                <li>✅ Vendez vos produits en quelques minutes</li>
            </ul>

            <Link :href="lien(true)" class="mt-4 block text-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                {{ texteCTAPrincipal }}
            </Link>
            <Link :href="lien(false)" class="mt-2 block text-center text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400">
                {{ texteCTASecondaire }}
            </Link>
            <button type="button" class="mt-2 block w-full text-center text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" @click="fermer">
                Plus tard
            </button>
        </div>
    </div>
</template>
