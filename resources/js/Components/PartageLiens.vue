<script setup>
import { ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    url: {
        type: String,
        required: true,
    },
    texte: {
        type: String,
        default: '',
    },
});

const copie = ref(false);

const copierLien = async () => {
    try {
        await navigator.clipboard.writeText(props.url);
        copie.value = true;
        setTimeout(() => { copie.value = false; }, 2000);
    } catch (e) {
        // Contexte non sécurisé ou permission refusée : on laisse l'utilisateur copier
        // le lien à la main, déjà affiché à l'écran.
    }
};

const lienWhatsapp = () => `https://wa.me/?text=${encodeURIComponent(`${props.texte} ${props.url}`)}`;
const lienFacebook = () => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.url)}`;
const lienTelegram = () => `https://t.me/share/url?url=${encodeURIComponent(props.url)}&text=${encodeURIComponent(props.texte)}`;

const peutPartageNatif = typeof navigator !== 'undefined' && !!navigator.share;
const partagerNatif = () => {
    navigator.share({ title: props.texte, url: props.url }).catch(() => {});
};

// Généré entièrement côté navigateur, à la demande seulement (jamais au chargement
// de la page) -- aucun aller-retour serveur, le lien ne quitte jamais le poste du
// visiteur avant d'être encodé.
const qrCodeUrl = ref(null);
const genereEnCours = ref(false);
const erreurQrCode = ref(false);

const afficherQrCode = async () => {
    if (qrCodeUrl.value || genereEnCours.value) {
        qrCodeUrl.value = null;
        return;
    }

    genereEnCours.value = true;
    erreurQrCode.value = false;
    try {
        qrCodeUrl.value = await QRCode.toDataURL(props.url, { width: 400, margin: 2 });
    } catch (e) {
        erreurQrCode.value = true;
    } finally {
        genereEnCours.value = false;
    }
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            @click="copierLien"
        >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
            </svg>
            {{ copie ? 'Lien copié !' : 'Copier le lien' }}
        </button>

        <a :href="lienWhatsapp()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-green-600 text-white shadow-sm hover:bg-green-700 hover:shadow-md transition-all duration-150">
            WhatsApp
        </a>
        <a :href="lienFacebook()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:shadow-md transition-all duration-150">
            Facebook
        </a>
        <a :href="lienTelegram()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-sky-500 text-white shadow-sm hover:bg-sky-600 hover:shadow-md transition-all duration-150">
            Telegram
        </a>
        <button
            v-if="peutPartageNatif"
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            @click="partagerNatif"
        >
            Autres...
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            :disabled="genereEnCours"
            @click="afficherQrCode"
        >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h6v6h-6v-6zm10.5 0h6v6h-6v-6zm-10.5 10.5h6v6h-6v-6zm10.5 3h2.25m0-3v6m0-6h-2.25m0 3h-3" />
            </svg>
            {{ qrCodeUrl ? 'Masquer le code QR' : (genereEnCours ? 'Génération...' : 'Code QR') }}
        </button>
    </div>

    <div v-if="erreurQrCode" class="mt-3 text-sm text-red-600 dark:text-red-400">
        Impossible de générer le code QR pour le moment. Réessayez.
    </div>

    <div v-if="qrCodeUrl" class="mt-3 inline-flex flex-col items-center gap-2 p-4 bg-white rounded-lg border border-slate-200 dark:border-slate-600">
        <img :src="qrCodeUrl" alt="Code QR du lien" class="size-40 sm:size-48">
        <a
            :href="qrCodeUrl"
            download="controool-qr-code.png"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-150"
        >
            Télécharger le code QR
        </a>
    </div>
</template>
