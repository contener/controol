<script setup>
import { ref } from 'vue';

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
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
            @click="copierLien"
        >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
            </svg>
            {{ copie ? 'Lien copié !' : 'Copier le lien' }}
        </button>

        <a :href="lienWhatsapp()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-green-600 text-white hover:bg-green-700">
            WhatsApp
        </a>
        <a :href="lienFacebook()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700">
            Facebook
        </a>
        <a :href="lienTelegram()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-sky-500 text-white hover:bg-sky-600">
            Telegram
        </a>
        <button
            v-if="peutPartageNatif"
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
            @click="partagerNatif"
        >
            Autres...
        </button>
    </div>
</template>
