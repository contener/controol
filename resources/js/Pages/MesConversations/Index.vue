<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConversationThread from '@/Components/ConversationThread.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    conversations: Object,
    conversationActive: { type: Object, default: null },
});

const mobileVueThread = ref(false);

const ouvrir = (conversation) => {
    router.get(route('mes-conversations.index'), { conversation: conversation.id }, {
        only: ['conversationActive', 'conversations'],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { mobileVueThread.value = true; },
    });
};

const formReponse = useForm({ contenu: '' });
const repondre = () => {
    formReponse.post(route('mes-conversations.repondre', props.conversationActive.id), {
        preserveScroll: true,
        onSuccess: () => formReponse.reset(),
    });
};

const extrait = (conversation) => conversation.dernier_message?.contenu ?? '';
const heureRelative = (date) => (date ? new Date(date).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '');
</script>

<template>
    <AppLayout title="Mes conversations">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Mes conversations</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden sm:flex" style="min-height: 32rem;">
                    <div class="sm:w-96 sm:border-r border-gray-200 dark:border-gray-700 overflow-y-auto" :class="{ 'hidden sm:block': mobileVueThread }">
                        <div v-if="conversations.data.length === 0" class="p-8 text-center text-gray-400 dark:text-gray-500 text-sm">
                            Vous n'avez contacté aucune boutique pour le moment.
                        </div>
                        <button
                            v-for="conversation in conversations.data"
                            :key="conversation.id"
                            type="button"
                            class="w-full text-left px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            :class="conversationActive?.id === conversation.id ? 'bg-indigo-50 dark:bg-indigo-900/20' : ''"
                            @click="ouvrir(conversation)"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">{{ conversation.boutique.nom }}</span>
                                <span v-if="conversation.messages_non_lus_visiteur > 0" class="shrink-0 inline-flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-semibold">
                                    {{ conversation.messages_non_lus_visiteur }}
                                </span>
                            </div>
                            <p v-if="conversation.produit" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ conversation.produit.nom }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ extrait(conversation) }}</p>
                            <span class="text-[11px] text-gray-400">{{ heureRelative(conversation.dernier_message_a) }}</span>
                        </button>

                        <div v-if="conversations.links.length > 3" class="p-2 flex flex-wrap gap-1">
                            <Link
                                v-for="(link, index) in conversations.links"
                                :key="index"
                                :href="link.url ?? '#'"
                                v-html="link.label"
                                class="px-2 py-1 text-xs rounded border"
                                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600'"
                                :disabled="!link.url"
                            />
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col" :class="{ 'hidden sm:flex': !mobileVueThread }">
                        <template v-if="conversationActive">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                <button type="button" class="sm:hidden text-sm text-indigo-600" @click="mobileVueThread = false">← Retour</button>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ conversationActive.boutique.nom }}</p>
                                    <p v-if="conversationActive.produit" class="text-xs text-gray-500 dark:text-gray-400">{{ conversationActive.produit.nom }}</p>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto p-4">
                                <ConversationThread :messages="conversationActive.messages" me="visiteur" />
                            </div>

                            <div class="p-3 border-t border-gray-200 dark:border-gray-700">
                                <textarea v-model="formReponse.contenu" rows="2" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Écrire une réponse..." />
                                <InputError :message="formReponse.errors.contenu" class="mt-1" />
                                <div class="mt-2 flex justify-end">
                                    <PrimaryButton :class="{ 'opacity-25': formReponse.processing }" :disabled="formReponse.processing" @click="repondre">Envoyer</PrimaryButton>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex-1 flex items-center justify-center text-gray-400 dark:text-gray-500 text-sm">
                            Sélectionnez une conversation pour l'afficher.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
