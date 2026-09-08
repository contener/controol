<script setup>
defineProps({
    messages: { type: Array, default: () => [] },
    me: { type: String, required: true }, // 'boutique' | 'visiteur'
});

const formatHeure = (date) => new Date(date).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <div class="space-y-3">
        <div v-if="messages.length === 0" class="text-sm text-slate-400 text-center py-6">
            Aucun message pour l'instant.
        </div>
        <div
            v-for="message in messages"
            :key="message.id"
            class="flex"
            :class="message.expediteur === me ? 'justify-end' : 'justify-start'"
        >
            <div
                class="max-w-[80%] rounded-lg px-3 py-2 text-sm"
                :class="message.expediteur === me
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-100'"
            >
                <p class="whitespace-pre-line">{{ message.contenu }}</p>
                <p class="mt-1 text-[11px] opacity-70">{{ formatHeure(message.created_at) }}</p>
            </div>
        </div>
    </div>
</template>
