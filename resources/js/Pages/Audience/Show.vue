<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import RelanceAudienceModal from './Partials/RelanceAudienceModal.vue';

const props = defineProps({
    membre: Object,
    produitsConsultes: Array,
    historique: Array,
    statuts: Array,
});

const typeLabels = {
    LIKE: '❤️ Like',
    MESSAGE: '💬 Message',
    WHATSAPP_RELANCE_OPENED: '📱 Relance WhatsApp ouverte',
    MESSAGE_RELANCE_ENVOYE: '💬 Relance par message envoyée',
};

const statutLabels = {
    actif: 'Actif',
    contacte: 'Contacté',
    interesse: 'Intéressé',
    converti: 'Converti',
    desinscrit: 'Désinscrit',
    bloque: 'Bloqué',
    archive: 'Archivé',
};

const statutSelectionne = ref(props.membre.statut);
watch(statutSelectionne, (statut) => {
    router.patch(route('audience.statut', props.membre.id), { statut }, { preserveScroll: true });
});

const relanceOuverte = ref(false);
const canalRelance = ref('whatsapp');
const ouvrirRelance = (canal) => {
    canalRelance.value = canal;
    relanceOuverte.value = true;
};
const messageInitial = computed(() => {
    const dernierProduit = props.produitsConsultes[0]?.nom;
    return dernierProduit
        ? `Bonjour ${props.membre.nom} 👋, vous aviez montré un intérêt pour notre produit "${dernierProduit}". Il est toujours disponible. Souhaitez-vous plus d'informations ?`
        : `Bonjour ${props.membre.nom} 👋, merci pour votre intérêt pour nos produits. N'hésitez pas si vous avez des questions !`;
});
</script>

<template>
    <AppLayout title="Audience">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">👥 {{ membre.nom }}</h2>
                <Link :href="route('audience.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Retour à l'audience</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm flex-1">
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400 uppercase">Nom</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-slate-100">{{ membre.nom }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400 uppercase">Contact</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-slate-100">{{ membre.contact ?? 'Non renseigné' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-slate-500 dark:text-slate-400 uppercase">Première interaction</dt>
                                <dd class="mt-0.5 text-slate-900 dark:text-slate-100">{{ membre.premiere_interaction_a }}</dd>
                            </div>
                        </dl>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                v-if="membre.contact"
                                type="button"
                                class="inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700"
                                @click="ouvrirRelance('whatsapp')"
                            >
                                📱 Relancer sur WhatsApp
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
                                @click="ouvrirRelance('message')"
                            >
                                💬 Relancer par message
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center gap-3">
                        <label for="statut" class="text-xs text-slate-500 dark:text-slate-400 uppercase">Statut</label>
                        <SelectInput id="statut" v-model="statutSelectionne" class="w-48">
                            <option v-for="s in statuts" :key="s" :value="s">{{ statutLabels[s] ?? s }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Produits consultés</h3>
                    <div v-if="produitsConsultes.length === 0" class="text-sm text-slate-400 dark:text-slate-500">Aucun produit associé.</div>
                    <ul v-else class="flex flex-wrap gap-2">
                        <li v-for="produit in produitsConsultes" :key="produit.id" class="px-3 py-1.5 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm rounded-md">
                            {{ produit.nom }}
                        </li>
                    </ul>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Historique des interactions</h3>
                    <div v-if="historique.length === 0" class="text-sm text-slate-400 dark:text-slate-500">Aucune interaction.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="interaction in historique" :key="interaction.id" class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-2">
                            <span class="text-slate-700 dark:text-slate-300">
                                {{ typeLabels[interaction.type] ?? interaction.type }}
                                <span v-if="interaction.produit_nom" class="text-slate-400 dark:text-slate-500">— {{ interaction.produit_nom }}</span>
                            </span>
                            <span class="text-slate-400 dark:text-slate-500 text-xs">{{ interaction.created_at }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <RelanceAudienceModal
            :show="relanceOuverte"
            :membre="membre"
            :message-initial="messageInitial"
            :canal="canalRelance"
            @close="relanceOuverte = false"
        />
    </AppLayout>
</template>
