<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ConversationThread from '@/Components/ConversationThread.vue';

const props = defineProps({
    show: Boolean,
    boutiqueSlug: { type: String, required: true },
    produit: { type: Object, default: null },
    conversation: { type: Object, default: null },
});
const emit = defineEmits(['close', 'sent']);

const page = usePage();
const lienConversation = computed(() => page.props.flash?.lien_conversation);

const form = useForm({
    nom_visiteur: '',
    contact_visiteur: '',
    contenu: '',
    produit_id: null,
});

watch(() => props.show, (visible) => {
    if (!visible) {
        return;
    }
    form.reset();
    form.clearErrors();
    form.produit_id = props.produit?.id ?? null;
    if (props.conversation) {
        form.nom_visiteur = props.conversation.visiteur_nom;
        form.contact_visiteur = props.conversation.visiteur_contact ?? '';
    } else if (props.produit) {
        form.contenu = `Bonjour, je suis intéressé(e) par "${props.produit.nom}".`;
    }
});

const envoyer = () => {
    form.post(route('public.boutique.messages.store', props.boutiqueSlug), {
        preserveScroll: true,
        onSuccess: () => {
            form.contenu = '';
            emit('sent');
        },
    });
};

const copierLien = () => {
    if (lienConversation.value) {
        navigator.clipboard?.writeText(lienConversation.value);
    }
};
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Contacter la boutique</template>
        <template #content>
            <p v-if="produit" class="text-sm text-gray-500 mb-4">À propos de « {{ produit.nom }} »</p>

            <ConversationThread v-if="conversation" :messages="conversation.messages" me="visiteur" class="mb-4 max-h-64 overflow-y-auto" />

            <div v-if="!conversation">
                <div>
                    <InputLabel for="nom_visiteur" value="Votre nom" />
                    <TextInput id="nom_visiteur" v-model="form.nom_visiteur" class="mt-1 block w-full" autocomplete="name" />
                    <InputError :message="form.errors.nom_visiteur" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="contact_visiteur" value="Téléphone ou email (optionnel)" />
                    <TextInput id="contact_visiteur" v-model="form.contact_visiteur" class="mt-1 block w-full" placeholder="Pour que le vendeur puisse vous répondre" />
                    <InputError :message="form.errors.contact_visiteur" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="contenu" :value="conversation ? 'Votre réponse' : 'Votre message'" />
                <textarea id="contenu" v-model="form.contenu" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.contenu" class="mt-2" />
            </div>

            <div v-if="lienConversation" class="mt-4 rounded-md bg-indigo-50 border border-indigo-200 px-3 py-2 text-xs text-indigo-800 flex items-center justify-between gap-2">
                <span>Conservez ce lien pour retrouver cette conversation depuis un autre appareil.</span>
                <button type="button" class="shrink-0 font-medium underline" @click="copierLien">Copier</button>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Fermer</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="envoyer">
                Envoyer
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
