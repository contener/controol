<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConversationThread from '@/Components/ConversationThread.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import NouvelleConversationModal from './Partials/NouvelleConversationModal.vue';

const props = defineProps({
    conversations: Object,
    conversationActive: { type: Object, default: null },
    clients: Array,
});

const mobileVueThread = ref(false);
const nouvelleConversationOuverte = ref(false);

const statutLabels = {
    ouverte: 'Ouverte',
    archivee: 'Archivée',
    fermee: 'Fermée',
    bloquee: 'Bloquée',
};

const ouvrir = (conversation) => {
    router.get(route('messages.index'), { conversation: conversation.id }, {
        only: ['conversationActive', 'conversations'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { mobileVueThread.value = true; },
    });
};

const formReponse = useForm({ contenu: '' });
const repondre = () => {
    formReponse.post(route('messages.repondre', props.conversationActive.id), {
        preserveScroll: true,
        onSuccess: () => formReponse.reset(),
    });
};

const changerStatut = (statut) => {
    router.patch(route('messages.statut', props.conversationActive.id), { statut }, { preserveScroll: true });
};

const extrait = (conversation) => conversation.dernier_message?.contenu ?? '';
const heureRelative = (date) => (date ? new Date(date).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '');
</script>

<template>
    <AppLayout title="Messages">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Messages</h2>
                <PrimaryButton @click="nouvelleConversationOuverte = true">+ Nouvelle conversation</PrimaryButton>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-hidden sm:flex" style="min-height: 32rem;">
                    <div class="sm:w-96 sm:border-r border-slate-200 dark:border-slate-700 overflow-y-auto" :class="{ 'hidden sm:block': mobileVueThread }">
                        <div v-if="conversations.data.length === 0" class="p-8 text-center text-slate-400 dark:text-slate-500 text-sm">
                            Aucune conversation pour le moment.
                        </div>
                        <button
                            v-for="conversation in conversations.data"
                            :key="conversation.id"
                            type="button"
                            class="w-full text-left px-4 py-3 border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            :class="conversationActive?.id === conversation.id ? 'bg-blue-50 dark:bg-blue-900/20' : ''"
                            @click="ouvrir(conversation)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-sm text-slate-900 dark:text-slate-100 truncate">{{ conversation.visiteur_nom }}</span>
                                <span v-if="conversation.messages_non_lus_boutique > 0" class="shrink-0 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                    {{ conversation.messages_non_lus_boutique }}
                                </span>
                            </div>
                            <p v-if="conversation.produit" class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ conversation.produit.nom }}</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 truncate mt-0.5">{{ extrait(conversation) }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <span class="text-[11px] text-slate-400">{{ heureRelative(conversation.dernier_message_a) }}</span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">{{ statutLabels[conversation.statut] }}</span>
                            </div>
                        </button>

                        <div v-if="conversations.links.length > 3" class="p-2 flex flex-wrap gap-1">
                            <Link
                                v-for="(link, index) in conversations.links"
                                :key="index"
                                :href="link.url ?? '#'"
                                v-html="link.label"
                                class="px-2 py-1 text-xs rounded border"
                                :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'"
                                :disabled="!link.url"
                            />
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col" :class="{ 'hidden sm:flex': !mobileVueThread }">
                        <template v-if="conversationActive">
                            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2 flex-wrap">
                                <button type="button" class="sm:hidden text-sm text-blue-600" @click="mobileVueThread = false">← Retour</button>
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ conversationActive.visiteur_nom }}</p>
                                    <p v-if="conversationActive.produit" class="text-xs text-slate-500 dark:text-slate-400">{{ conversationActive.produit.nom }}</p>
                                </div>
                                <div class="flex gap-2 text-xs">
                                    <button v-if="conversationActive.statut !== 'archivee'" type="button" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300" @click="changerStatut('archivee')">Archiver</button>
                                    <button v-if="conversationActive.statut !== 'ouverte'" type="button" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300" @click="changerStatut('ouverte')">Rouvrir</button>
                                    <button v-if="conversationActive.statut !== 'fermee'" type="button" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300" @click="changerStatut('fermee')">Fermer</button>
                                    <button v-if="conversationActive.statut !== 'bloquee'" type="button" class="px-2 py-1 rounded border border-red-300 text-red-600" @click="changerStatut('bloquee')">Bloquer</button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto p-4">
                                <ConversationThread :messages="conversationActive.messages" me="boutique" />
                            </div>

                            <div v-if="conversationActive.statut !== 'bloquee'" class="p-3 border-t border-slate-200 dark:border-slate-700">
                                <textarea v-model="formReponse.contenu" rows="2" class="block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm" placeholder="Écrire une réponse..." />
                                <InputError :message="formReponse.errors.contenu" class="mt-1" />
                                <div class="mt-2 flex justify-end">
                                    <PrimaryButton :class="{ 'opacity-25': formReponse.processing }" :disabled="formReponse.processing" @click="repondre">Envoyer</PrimaryButton>
                                </div>
                            </div>
                            <div v-else class="p-3 border-t border-slate-200 dark:border-slate-700 text-xs text-slate-400 text-center">
                                Conversation bloquée — ce visiteur ne peut plus vous écrire.
                            </div>
                        </template>
                        <div v-else class="flex-1 flex items-center justify-center text-slate-400 dark:text-slate-500 text-sm">
                            Sélectionnez une conversation pour l'afficher.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <NouvelleConversationModal :show="nouvelleConversationOuverte" :clients="clients" @close="nouvelleConversationOuverte = false" />
    </AppLayout>
</template>
