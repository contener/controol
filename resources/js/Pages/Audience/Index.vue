<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    autorise: Boolean,
    statistiques: Object,
    membres: Object,
    filtres: Object,
    produitsDisponibles: { type: Array, default: () => [] },
});

const recherche = ref(props.filtres.recherche ?? '');
const produitId = ref(props.filtres.produit_id ?? '');
const type = ref(props.filtres.type ?? '');
const statut = ref(props.filtres.statut ?? '');

const appliquerFiltres = () => {
    router.get(route('audience.index'), {
        recherche: recherche.value,
        produit_id: produitId.value,
        type: type.value,
        statut: statut.value,
    }, { preserveState: true, replace: true });
};

watch([produitId, type, statut], appliquerFiltres);

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});

const typeLabels = {
    LIKE: '❤️ Like',
    MESSAGE: '💬 Message',
    WHATSAPP_RELANCE_OPENED: '📱 Relance WhatsApp',
    MESSAGE_RELANCE_ENVOYE: '💬 Relance par message',
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
</script>

<template>
    <AppLayout title="Audience">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">👥 Audience</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Les personnes qui ont montré un intérêt pour vos produits (like, message...) — relancez-les pour transformer cet intérêt en vente.
                </p>

                <div class="relative">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4" :class="{ 'blur-sm select-none pointer-events-none': !autorise }">
                        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">👥 Audience totale</div>
                            <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.total }}</div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">❤️ Ont aimé</div>
                            <div class="mt-1 text-2xl font-semibold text-pink-600 dark:text-pink-400">{{ statistiques.likes }}</div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">💬 Ont écrit</div>
                            <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ statistiques.messages }}</div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">📈 Nouvelles (7j)</div>
                            <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ statistiques.nouvelles_7_jours }}</div>
                        </div>
                    </div>

                    <div v-if="!autorise" class="absolute inset-0 flex flex-col items-center justify-center text-center gap-2 px-4">
                        <span class="text-2xl">🔒</span>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            Débloquez votre audience avec le plan Basique (5 000 FCFA/mois)
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md">
                            Voyez qui s'intéresse à vos produits, suivez leurs interactions et relancez-les directement sur WhatsApp.
                        </p>
                        <Link :href="route('abonnement.index')">
                            <PrimaryButton class="mt-1">🚀 Passer au plan Basic</PrimaryButton>
                        </Link>
                    </div>
                </div>

                <template v-if="autorise">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                        <TextInput v-model="recherche" placeholder="Rechercher par nom..." class="flex-1" />
                        <SelectInput v-model="produitId" class="sm:w-56">
                            <option value="">Tous les produits</option>
                            <option v-for="produit in produitsDisponibles" :key="produit.id" :value="produit.id">{{ produit.nom }}</option>
                        </SelectInput>
                        <SelectInput v-model="type" class="sm:w-48">
                            <option value="">Toutes les interactions</option>
                            <option value="LIKE">❤️ Like</option>
                            <option value="MESSAGE">💬 Message</option>
                        </SelectInput>
                        <SelectInput v-model="statut" class="sm:w-48">
                            <option value="">Tous les statuts</option>
                            <option v-for="(label, cle) in statutLabels" :key="cle" :value="cle">{{ label }}</option>
                        </SelectInput>
                    </div>

                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Dernier produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Interaction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Dernière activité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Interactions</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-if="membres.data.length === 0">
                                    <td colspan="6" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Personne dans votre audience pour le moment.</td>
                                </tr>
                                <tr v-for="membre in membres.data" :key="membre.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">
                                        <Link :href="route('audience.show', membre.id)" class="hover:text-blue-600 dark:hover:text-blue-400">{{ membre.nom }}</Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ membre.dernier_produit ?? '—' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ typeLabels[membre.derniere_interaction_type] ?? membre.derniere_interaction_type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ membre.derniere_interaction_a }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ membre.total_interactions }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <Link :href="route('audience.show', membre.id)" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium">Relancer</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="membres.links.length > 3" class="flex flex-wrap gap-1">
                        <Link
                            v-for="(link, index) in membres.links"
                            :key="index"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            :disabled="!link.url"
                        />
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
