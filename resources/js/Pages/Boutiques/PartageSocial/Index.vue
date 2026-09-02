<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    destinations: Array,
    campagne: Object,
    autorise: Boolean,
    peutAjouterDestination: Boolean,
    limiteDestinations: Number,
    boutique: Object,
});

const VARIABLES = [
    { code: '{{shop_name}}', label: 'Nom boutique' },
    { code: '{{shop_url}}', label: 'Lien boutique' },
    { code: '{{product_name}}', label: 'Nom produit' },
    { code: '{{product_price}}', label: 'Prix produit' },
];

const statutLabels = {
    en_attente: 'En attente',
    en_cours: 'En cours',
    envoye: 'Envoyé',
    echec: 'Échec',
    non_autorise: 'Non autorisé',
};
const statutClasses = {
    en_attente: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
    en_cours: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
    envoye: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    echec: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
    non_autorise: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
};

// --- Destinations CRUD ---
const formDestination = useForm({ nom: '', lien: '', type: '' });
const destinationEnEdition = ref(null);
const afficherFormDestination = ref(false);

const ouvrirAjoutDestination = () => {
    destinationEnEdition.value = null;
    formDestination.reset();
    afficherFormDestination.value = true;
};

const ouvrirEditionDestination = (destination) => {
    destinationEnEdition.value = destination;
    formDestination.nom = destination.nom;
    formDestination.lien = destination.lien;
    formDestination.type = destination.type ?? '';
    afficherFormDestination.value = true;
};

const enregistrerDestination = () => {
    if (destinationEnEdition.value) {
        formDestination.put(route('destinations-sociales.update', destinationEnEdition.value.id), {
            onSuccess: () => { afficherFormDestination.value = false; },
        });
    } else {
        formDestination.post(route('destinations-sociales.store'), {
            onSuccess: () => { afficherFormDestination.value = false; },
        });
    }
};

const supprimerDestination = (destination) => {
    if (confirm(`Supprimer la destination "${destination.nom}" ?`)) {
        router.delete(route('destinations-sociales.destroy', destination.id));
    }
};

// --- Composeur de message ---
const formCampagne = useForm({
    message: "Découvrez {{shop_name}} !\n\nRetrouvez tous nos produits ici :\n{{shop_url}}",
    intervalle_secondes: 30,
});

const insererVariable = (code) => {
    formCampagne.message += (formCampagne.message.endsWith('\n') || formCampagne.message === '' ? '' : ' ') + code;
};

const apercuMessage = computed(() => {
    return formCampagne.message
        .replaceAll('{{shop_name}}', props.boutique?.nom ?? '')
        .replaceAll('{{shop_url}}', route('public.boutique', props.boutique?.slug ?? ''));
});

// --- Campagne ---
const campagneEnCours = computed(() => props.campagne && props.campagne.statut === 'en_cours');
const destinationsTraitees = computed(() => {
    if (!props.campagne) return 0;
    return props.campagne.campagne_destinations.filter((d) => d.statut !== 'en_attente').length;
});
const prochaineDestination = computed(() => {
    if (!campagneEnCours.value) return null;
    return props.campagne.campagne_destinations.find((d) => d.statut === 'en_attente') ?? null;
});
const messagePourDestination = computed(() => {
    if (!prochaineDestination.value) return '';
    return props.campagne.message
        .replaceAll('{{shop_name}}', props.boutique?.nom ?? '')
        .replaceAll('{{shop_url}}', route('public.boutique', props.boutique?.slug ?? ''));
});

const copie = ref(false);
const copierMessage = async () => {
    try {
        await navigator.clipboard.writeText(messagePourDestination.value);
        copie.value = true;
        setTimeout(() => { copie.value = false; }, 2000);
    } catch (e) { /* copie manuelle possible depuis le texte affiché */ }
};

const showDemarrer = ref(false);
const demarrerCampagne = () => {
    formCampagne.post(route('campagnes-sociales.demarrer'), {
        onSuccess: () => { showDemarrer.value = false; },
    });
};

const arreterCampagne = () => {
    if (confirm('Arrêter la campagne en cours ?')) {
        router.post(route('campagnes-sociales.arreter', props.campagne.id));
    }
};

const confirmerDestination = (campagneDestination, statut) => {
    router.post(route('campagne-destinations.confirmer', campagneDestination.id), { statut });
};
</script>

<template>
    <AppLayout title="Partage & publication sociale">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Partage &amp; publication sociale</h2>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="!autorise" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <p class="font-medium text-gray-900 dark:text-gray-100">Publication sociale indisponible</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Votre abonnement actuel ne permet pas cette fonctionnalité.</p>
                    <Link :href="route('abonnement.index')" class="mt-4 inline-block">
                        <PrimaryButton>Mettre à niveau</PrimaryButton>
                    </Link>
                </div>

                <template v-else>
                    <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-800 dark:text-blue-300">
                        Cet outil vous aide à préparer et suivre vos publications : il ne publie jamais automatiquement à votre place.
                        Vous copiez le message, l'envoyez vous-même sur chaque destination, puis confirmez le résultat.
                    </div>

                    <!-- Campagne en cours : flux guidé -->
                    <div v-if="campagneEnCours" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Publication en cours...</h3>
                            <SecondaryButton @click="arreterCampagne">■ Arrêter</SecondaryButton>
                        </div>

                        <div class="mb-4">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span>{{ destinationsTraitees }} / {{ campagne.campagne_destinations.length }} destinations traitées</span>
                            </div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-600 transition-all" :style="`width: ${(destinationsTraitees / campagne.campagne_destinations.length) * 100}%`" />
                            </div>
                        </div>

                        <div v-if="prochaineDestination" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Prochaine destination : {{ prochaineDestination.destination.nom }}</p>

                            <div class="mt-3 bg-gray-50 dark:bg-gray-900 rounded-md p-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ messagePourDestination }}</div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <SecondaryButton @click="copierMessage">{{ copie ? 'Copié !' : 'Copier le message' }}</SecondaryButton>
                                <a :href="prochaineDestination.destination.lien" target="_blank" rel="noopener">
                                    <SecondaryButton type="button">Ouvrir la destination</SecondaryButton>
                                </a>
                            </div>

                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Une fois le message publié à la main sur cette destination, confirmez le résultat :</p>
                            <div class="mt-2 flex gap-2">
                                <button class="px-3 py-1.5 text-sm font-medium rounded-md bg-green-600 text-white hover:bg-green-700" @click="confirmerDestination(prochaineDestination, 'envoye')">
                                    ✓ Envoyé
                                </button>
                                <button class="px-3 py-1.5 text-sm font-medium rounded-md bg-red-600 text-white hover:bg-red-700" @click="confirmerDestination(prochaineDestination, 'echec')">
                                    ✗ Échec
                                </button>
                                <button class="px-3 py-1.5 text-sm font-medium rounded-md bg-yellow-500 text-white hover:bg-yellow-600" @click="confirmerDestination(prochaineDestination, 'non_autorise')">
                                    Non autorisé
                                </button>
                            </div>
                        </div>

                        <div v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">
                            Toutes les destinations ont été traitées.
                        </div>
                    </div>

                    <!-- Composeur de message (hors campagne active) -->
                    <div v-else class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Message à publier</h3>

                        <textarea
                            v-model="formCampagne.message"
                            rows="5"
                            class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        />
                        <InputError :message="formCampagne.errors.message" class="mt-2" />

                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="variable in VARIABLES"
                                :key="variable.code"
                                type="button"
                                class="px-2 py-1 text-xs font-mono rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="insererVariable(variable.code)"
                            >
                                {{ variable.code }}
                            </button>
                        </div>

                        <div class="mt-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Aperçu :</p>
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-md p-3 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ apercuMessage }}</div>
                        </div>

                        <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">⚙ Paramètres de publication</h4>
                            <div class="flex items-center gap-2">
                                <InputLabel for="intervalle" value="Intervalle entre publications" class="mb-0" />
                                <TextInput id="intervalle" v-model="formCampagne.intervalle_secondes" type="number" min="5" max="3600" class="w-24" />
                                <span class="text-sm text-gray-500 dark:text-gray-400">secondes</span>
                            </div>
                            <InputError :message="formCampagne.errors.intervalle_secondes" class="mt-2" />
                        </div>

                        <div class="mt-4 flex justify-end">
                            <PrimaryButton :disabled="destinations.length === 0" @click="showDemarrer = true">
                                ▶ Démarrer la publication
                            </PrimaryButton>
                        </div>
                        <p v-if="destinations.length === 0" class="mt-2 text-xs text-gray-400 dark:text-gray-500 text-right">Ajoutez au moins une destination ci-dessous.</p>
                    </div>

                    <!-- Tableau des destinations -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                        <div class="p-6 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Groupes / Destinations de publication</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1" v-if="limiteDestinations !== null">{{ destinations.length }} / {{ limiteDestinations }} destinations utilisées</p>
                            </div>
                            <SecondaryButton :disabled="!peutAjouterDestination" @click="ouvrirAjoutDestination">+ Ajouter une destination</SecondaryButton>
                        </div>
                        <p v-if="!peutAjouterDestination" class="px-6 pb-4 -mt-2 text-xs text-yellow-700 dark:text-yellow-400">Limite de destinations atteinte pour votre plan.</p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nom du groupe</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Lien</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Statut</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="destinations.length === 0">
                                        <td colspan="4" class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">Aucune destination enregistrée.</td>
                                    </tr>
                                    <tr v-for="destination in destinations" :key="destination.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ destination.nom }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                            <a :href="destination.lien" target="_blank" rel="noopener" class="hover:underline">{{ destination.lien }}</a>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[destination.statut]">{{ statutLabels[destination.statut] }}</span>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-right text-sm space-x-3">
                                            <button class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300" @click="ouvrirEditionDestination(destination)">Modifier</button>
                                            <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="supprimerDestination(destination)">Supprimer</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modale ajout/édition destination -->
        <ConfirmationModal :show="afficherFormDestination" @close="afficherFormDestination = false">
            <template #title>{{ destinationEnEdition ? 'Modifier la destination' : 'Ajouter une destination' }}</template>
            <template #content>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="dest_nom" value="Nom du groupe *" />
                        <TextInput id="dest_nom" v-model="formDestination.nom" type="text" class="mt-1 block w-full" placeholder="Groupe Vente Douala" />
                        <InputError :message="formDestination.errors.nom" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="dest_lien" value="Lien du groupe *" />
                        <TextInput id="dest_lien" v-model="formDestination.lien" type="url" class="mt-1 block w-full" placeholder="https://chat.whatsapp.com/..." />
                        <InputError :message="formDestination.errors.lien" class="mt-2" />
                    </div>
                </div>
            </template>
            <template #footer>
                <SecondaryButton @click="afficherFormDestination = false">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="formDestination.processing" @click="enregistrerDestination">Enregistrer</PrimaryButton>
            </template>
        </ConfirmationModal>

        <!-- Confirmation démarrage campagne -->
        <ConfirmationModal :show="showDemarrer" @close="showDemarrer = false">
            <template #title>Démarrer la publication ?</template>
            <template #content>
                Vous allez être guidé·e destination par destination ({{ destinations.length }} au total) pour publier votre message manuellement.
            </template>
            <template #footer>
                <SecondaryButton @click="showDemarrer = false">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" :disabled="formCampagne.processing" @click="demarrerCampagne">Démarrer</PrimaryButton>
            </template>
        </ConfirmationModal>
    </AppLayout>
</template>
