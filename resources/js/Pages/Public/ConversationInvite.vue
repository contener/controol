<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import ConversationThread from '@/Components/ConversationThread.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import FlashMessages from '@/Components/FlashMessages.vue';

const props = defineProps({
    conversation: Object,
});

const form = useForm({ contenu: '' });

const repondre = () => {
    form.post(route('public.conversations.repondre', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Votre conversation" />

    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-100">
            <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationMark class="h-6 w-auto" />
                    <span class="text-sm font-semibold text-gray-900 hidden sm:inline">Controol</span>
                </Link>
                <Link :href="route('marketplace.index')" class="text-sm font-medium text-gray-600 hover:text-gray-900">Marketplace</Link>
            </div>
        </header>

        <FlashMessages />

        <div class="max-w-2xl mx-auto px-4 py-8">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <img v-if="conversation.boutique.logo_path" :src="`/storage/${conversation.boutique.logo_path}`" class="h-10 w-10 rounded-full object-cover" :alt="conversation.boutique.nom">
                    <div>
                        <h1 class="font-semibold text-gray-900">{{ conversation.boutique.nom }}</h1>
                        <p v-if="conversation.produit" class="text-xs text-gray-500">À propos de « {{ conversation.produit.nom }} »</p>
                    </div>
                </div>

                <ConversationThread :messages="conversation.messages" me="visiteur" class="max-h-96 overflow-y-auto" />

                <div v-if="conversation.statut === 'bloquee'" class="mt-4 text-sm text-gray-400 text-center border-t border-gray-100 pt-4">
                    Cette conversation n'accepte plus de nouveaux messages.
                </div>
                <div v-else class="mt-4 border-t border-gray-100 pt-4">
                    <textarea v-model="form.contenu" rows="3" class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Écrire une réponse..." />
                    <InputError :message="form.errors.contenu" class="mt-1" />
                    <div class="mt-2 flex justify-end">
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="repondre">Envoyer</PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
