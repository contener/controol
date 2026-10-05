<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    membre: { type: Object, default: null }, // { id, nom }
    messageInitial: { type: String, default: '' },
    canal: { type: String, default: 'whatsapp' }, // 'whatsapp' ou 'message'
});
const emit = defineEmits(['close']);

const message = ref('');
const enCours = ref(false);
const erreur = ref('');

watch(() => props.show, (visible) => {
    if (visible) {
        message.value = props.messageInitial;
        erreur.value = '';
    }
});

// N'utilise pas router.post() (Inertia) pour WhatsApp exprès : on ne veut pas naviguer
// avant l'ouverture du nouvel onglet -- même pattern que RelanceWhatsappModal.vue
// (Super Admin), simplifié : pas de modèle à choisir, pas d'étape de confirmation
// "envoyé" (l'ouverture de WhatsApp signifie uniquement que la relance est préparée).
const ouvrirWhatsapp = async () => {
    enCours.value = true;
    erreur.value = '';

    try {
        const { data } = await axios.post(route('audience.whatsapp', props.membre.id), { message: message.value });
        window.open(data.lien, '_blank');
        emit('close');
    } catch (e) {
        erreur.value = e.response?.data?.message ?? "Impossible d'ouvrir WhatsApp pour le moment.";
    } finally {
        enCours.value = false;
    }
};

// Le message interne, lui, est réellement envoyé côté serveur (pas juste "préparé")
// -- via Inertia, comme toute autre action serveur de cette page.
const envoyerMessage = () => {
    enCours.value = true;
    erreur.value = '';

    router.post(route('audience.message', props.membre.id), { message: message.value }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onError: (errors) => { erreur.value = errors.message ?? "Impossible d'envoyer le message pour le moment."; },
        onFinish: () => { enCours.value = false; },
    });
};

const envoyer = () => {
    if (!message.value.trim()) {
        erreur.value = 'Le message ne peut pas être vide.';
        return;
    }

    if (props.canal === 'whatsapp') {
        ouvrirWhatsapp();
    } else {
        envoyerMessage();
    }
};

const texteBouton = computed(() => (props.canal === 'whatsapp' ? '📱 Ouvrir WhatsApp' : '💬 Envoyer le message'));
const texteAide = computed(() => (props.canal === 'whatsapp'
    ? "WhatsApp s'ouvrira dans un nouvel onglet avec ce message prérempli — vous devrez l'envoyer vous-même depuis WhatsApp."
    : "Le message est envoyé directement dans la conversation avec cette personne — visible dès qu'elle revisite votre boutique."));
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Relancer {{ membre?.nom }}</template>
        <template #content>
            <div>
                <InputLabel for="message_relance" value="Message" />
                <textarea
                    id="message_relance"
                    v-model="message"
                    rows="5"
                    class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                />
                <InputError :message="erreur" class="mt-2" />
            </div>
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">{{ texteAide }}</p>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="envoyer">
                {{ texteBouton }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
