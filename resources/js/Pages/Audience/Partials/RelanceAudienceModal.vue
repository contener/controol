<script setup>
import { ref, watch } from 'vue';
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

// N'utilise pas router.post() (Inertia) exprès : on ne veut pas naviguer avant
// l'ouverture du nouvel onglet WhatsApp -- même pattern que RelanceWhatsappModal.vue
// (Super Admin), simplifié : pas de modèle à choisir, pas d'étape de confirmation
// "envoyé" (l'ouverture de WhatsApp signifie uniquement que la relance est préparée).
const ouvrirWhatsapp = async () => {
    if (!message.value.trim()) {
        erreur.value = 'Le message ne peut pas être vide.';
        return;
    }

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
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                WhatsApp s'ouvrira dans un nouvel onglet avec ce message prérempli — vous devrez l'envoyer vous-même depuis WhatsApp.
            </p>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="ouvrirWhatsapp">
                📱 Ouvrir WhatsApp
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
