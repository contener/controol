<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const DISMISS_KEY = 'controol_install_banner_dismissed';

const show = ref(false);
const etat = ref('install'); // 'install' (pas encore installée) ou 'update' (déjà installée)
const plateforme = ref('chrome'); // 'chrome' (bouton direct) ou 'ios' (instructions manuelles)
const miseAJourEnCours = ref(false);
let deferredPrompt = null;

const estInstallee = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const estIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

// Le refus de l'installation reste mémorisé (ne pas insister après un "non" explicite) --
// mais ne s'applique jamais à l'état "update" : une fois l'app installée, le bandeau
// reste une simple commande utilitaire ("vérifier les mises à jour"), toujours visible.
const dejaRefusee = () => {
    try {
        return localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
};

const onBeforeInstallPrompt = (event) => {
    event.preventDefault();
    deferredPrompt = event;
    if (etat.value !== 'update') {
        plateforme.value = 'chrome';
        show.value = true;
    }
};

const installer = async () => {
    if (!deferredPrompt) {
        return;
    }
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    etat.value = 'update';
    plateforme.value = estIos() ? 'ios' : 'chrome';
};

// Force le service worker à vérifier une nouvelle version, vide le cache des fichiers
// statiques (/build/assets/, voir public/sw.js) et recharge -- garantit la dernière
// version déployée, que l'app soit ouverte depuis l'icône installée ou le navigateur.
const mettreAJour = async () => {
    miseAJourEnCours.value = true;
    try {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            await registration?.update();
        }
        if ('caches' in window) {
            const cles = await caches.keys();
            await Promise.all(cles.map((cle) => caches.delete(cle)));
        }
    } finally {
        window.location.reload();
    }
};

const fermer = () => {
    show.value = false;
    if (etat.value === 'update') {
        return; // Dismiss pour cette visite seulement -- réapparaît à la prochaine ouverture.
    }
    try {
        localStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // Stockage indisponible (navigation privée...) : le bandeau peut réapparaître,
        // sans gravité.
    }
};

onMounted(() => {
    if (estInstallee()) {
        etat.value = 'update';
        plateforme.value = estIos() ? 'ios' : 'chrome';
        show.value = true;
        return;
    }

    if (dejaRefusee()) {
        return;
    }

    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);

    // iOS Safari ne déclenche jamais beforeinstallprompt -- seule façon d'installer :
    // Partager > "Sur l'écran d'accueil", à afficher comme instruction manuelle.
    if (estIos()) {
        plateforme.value = 'ios';
        show.value = true;
    }
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
});
</script>

<template>
    <div v-if="show" class="bg-blue-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="shrink-0 flex items-center justify-center size-8 rounded-lg bg-white/15">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </span>
                <p class="text-sm text-white truncate">
                    <template v-if="etat === 'update'">
                        Application déjà installée. Vérifiez que vous avez la dernière version.
                    </template>
                    <template v-else-if="plateforme === 'chrome'">
                        Installez Controol sur votre téléphone pour y accéder comme une vraie application.
                    </template>
                    <template v-else>
                        Installez Controol : appuyez sur <strong>Partager</strong> puis <strong>« Sur l'écran d'accueil »</strong>.
                    </template>
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <button
                    v-if="etat === 'update'"
                    type="button"
                    :disabled="miseAJourEnCours"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md bg-white text-blue-700 hover:bg-blue-50 disabled:opacity-60"
                    @click="mettreAJour"
                >
                    {{ miseAJourEnCours ? 'Mise à jour...' : 'Mettre à jour' }}
                </button>
                <button
                    v-else-if="plateforme === 'chrome'"
                    type="button"
                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-md bg-white text-blue-700 hover:bg-blue-50"
                    @click="installer"
                >
                    Installer
                </button>
                <button type="button" class="p-1.5 rounded-md text-white/80 hover:text-white hover:bg-white/10" aria-label="Fermer" @click="fermer">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
