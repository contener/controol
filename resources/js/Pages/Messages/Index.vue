<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    messages: Object,
});

const marquerLu = (message) => {
    if (message.lu) {
        return;
    }
    router.patch(route('messages.lu', message.id), {}, { preserveScroll: true });
};

const supprimer = (message) => {
    if (confirm(`Supprimer le message de "${message.nom_visiteur}" ?`)) {
        router.delete(route('messages.destroy', message.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout title="Messages">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Messages</h2>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div v-if="messages.data.length === 0" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center text-gray-400 dark:text-gray-500">
                    Aucun message reçu pour le moment.
                </div>

                <div
                    v-for="message in messages.data"
                    :key="message.id"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-start gap-3 border-s-4"
                    :class="message.lu ? 'border-transparent' : 'border-indigo-500'"
                    @click="marquerLu(message)"
                >
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ message.nom_visiteur }}</span>
                            <span v-if="!message.lu" class="px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Nouveau</span>
                            <span v-if="message.produit" class="text-xs text-gray-500 dark:text-gray-400">à propos de « {{ message.produit.nom }} »</span>
                        </div>
                        <p v-if="message.contact_visiteur" class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Contact : {{ message.contact_visiteur }}</p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ message.contenu }}</p>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ new Date(message.created_at).toLocaleString('fr-FR') }}</p>
                    </div>
                    <button class="shrink-0 text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 text-sm" @click.stop="supprimer(message)">
                        Supprimer
                    </button>
                </div>

                <div v-if="messages.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in messages.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
