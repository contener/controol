<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Relance groupée : message interne uniquement (la cloche, pas WhatsApp -- wa.me
// n'ouvre qu'une conversation à la fois, donc n'a pas de sens pour plusieurs
// destinataires en un clic). {{nom}}/{{prenom}} sont substitués côté serveur, une
// seule fois par destinataire -- jamais pré-remplis ici puisqu'il n'y a pas un seul nom
// à afficher mais autant de noms que de personnes sélectionnées.
const props = defineProps({
    show: Boolean,
    utilisateurIds: { type: Array, default: () => [] },
    nombreSelectionnes: { type: Number, default: 0 },
});
const emit = defineEmits(['close', 'envoye']);

const message = ref('');
const enCours = ref(false);
const erreur = ref('');
const exemplePlaceholder = '{{nom}}';

watch(() => props.show, (visible) => {
    if (visible) {
        message.value = '';
        erreur.value = '';
    }
});

const envoyer = () => {
    if (!message.value.trim()) {
        erreur.value = 'Le message ne peut pas être vide.';
        return;
    }

    enCours.value = true;
    erreur.value = '';

    router.post(route('admin.utilisateurs.message.massif'), {
        message: message.value,
        utilisateur_ids: props.utilisateurIds,
    }, {
        preserveScroll: true,
        onSuccess: () => emit('envoye'),
        onError: (errors) => { erreur.value = errors.message ?? errors.utilisateur_ids ?? "Impossible d'envoyer le message pour le moment."; },
        onFinish: () => { enCours.value = false; },
    });
};
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Relancer {{ nombreSelectionnes }} utilisateur{{ nombreSelectionnes > 1 ? 's' : '' }}</template>
        <template #content>
            <p class="text-sm text-slate-700 dark:text-slate-300">
                Un seul message, personnalisé automatiquement pour chaque destinataire.
            </p>

            <div class="mt-4">
                <InputLabel for="message_massif" value="Message" />
                <textarea
                    id="message_massif"
                    v-model="message"
                    rows="5"
                    placeholder="Bonjour {{nom}}, ..."
                    class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                />
                <InputError :message="erreur" class="mt-2" />
            </div>

            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                Utilisez {{ exemplePlaceholder }} dans le texte : chaque utilisateur recevra le message avec son propre
                nom déjà inséré, déposé immédiatement sur sa cloche de notification, au nom de l'équipe technique de
                Controol.
            </p>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="envoyer">
                💬 Envoyer à {{ nombreSelectionnes }} utilisateur{{ nombreSelectionnes > 1 ? 's' : '' }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
