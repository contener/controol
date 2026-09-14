<script setup>
import { onUnmounted, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    boutique: Object,
    agent: Object,
    autorise: Boolean,
    connecteurConfigure: Boolean,
});

const form = useForm({
    nom: props.agent.nom,
    langue: props.agent.langue,
    personnalite: props.agent.personnalite,
    ton: props.agent.ton,
    message_accueil: props.agent.message_accueil,
    message_hors_horaires: props.agent.message_hors_horaires,
});

const enregistrer = () => {
    form.patch(route('whatsapp-agent.update'), { preserveScroll: true });
};

const basculerStatut = () => {
    if (!props.autorise && !props.agent.actif) return;
    router.patch(route('whatsapp-agent.statut'), { actif: !props.agent.actif }, { preserveScroll: true });
};

const statistiques = [
    { libelle: 'Conversations aujourd\'hui', valeur: 0 },
    { libelle: 'Messages traités', valeur: 0 },
    { libelle: 'Clients qualifiés', valeur: 0 },
    { libelle: 'Conversations transférées', valeur: 0 },
];

// --- Connexion WhatsApp ---
const connexionStatut = ref(props.agent.whatsapp_statut ?? 'deconnecte');
const qrDataUri = ref(null);
const numeroConnecte = ref(props.agent.whatsapp_numero ?? null);
const connexionEnCours = ref(false);
let intervalleSondage = null;

const libellesStatutWhatsapp = {
    deconnecte: '⚪ Non connecté',
    connexion: '🟡 Connexion en cours...',
    qr: '🟡 En attente de scan',
    connecte: '🟢 Connecté',
    indisponible: '⚪ Service indisponible',
};

const appliquerEtat = (etat) => {
    connexionStatut.value = etat.statut ?? 'deconnecte';
    qrDataUri.value = etat.qr ?? null;
    numeroConnecte.value = etat.numero ?? null;

    if (['connexion', 'qr'].includes(connexionStatut.value)) {
        demarrerSondage();
    } else {
        arreterSondage();
    }
};

const demarrerSondage = () => {
    if (intervalleSondage) return;
    intervalleSondage = setInterval(async () => {
        const { data } = await window.axios.get(route('whatsapp-agent.connexion.statut'));
        appliquerEtat(data);
    }, 2500);
};

const arreterSondage = () => {
    if (intervalleSondage) {
        clearInterval(intervalleSondage);
        intervalleSondage = null;
    }
};

const connecterWhatsapp = async () => {
    connexionEnCours.value = true;
    try {
        const { data } = await window.axios.post(route('whatsapp-agent.connecter'));
        appliquerEtat(data);
    } finally {
        connexionEnCours.value = false;
    }
};

const deconnecterWhatsapp = async () => {
    if (!confirm('Déconnecter WhatsApp de cette boutique ?')) return;
    const { data } = await window.axios.post(route('whatsapp-agent.deconnecter'));
    appliquerEtat(data);
};

onUnmounted(arreterSondage);
</script>

<template>
    <AppLayout title="Agent IA WhatsApp">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🤖 Agent IA WhatsApp</h2>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <PageHeader titre="Agent IA WhatsApp" :description="`Boutique : ${boutique.nom}`" />

                <div v-if="!autorise" class="rounded-lg bg-slate-100 dark:bg-slate-800 px-6 py-4 text-sm text-slate-600 dark:text-slate-300 flex flex-wrap items-center justify-between gap-3">
                    <span>🤖 L'Agent IA WhatsApp permet de gérer automatiquement vos conversations clients. Disponible avec un abonnement compatible.</span>
                    <Link :href="route('abonnement.index')">
                        <SecondaryButton>Voir les forfaits</SecondaryButton>
                    </Link>
                </div>

                <!-- Tableau de bord -->
                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <div class="flex flex-wrap items-center gap-6">
                        <div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">Statut de l'agent</div>
                            <Badge :couleur="agent.actif ? 'green' : 'slate'">{{ agent.actif ? '🟢 Actif' : '⚪ Inactif' }}</Badge>
                        </div>
                        <div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase mb-1">WhatsApp</div>
                            <Badge :couleur="connexionStatut === 'connecte' ? 'green' : 'slate'">{{ libellesStatutWhatsapp[connexionStatut] ?? libellesStatutWhatsapp.deconnecte }}</Badge>
                        </div>
                        <div class="ms-auto">
                            <button
                                type="button"
                                class="text-sm font-medium"
                                :class="!autorise && !agent.actif ? 'text-slate-400 dark:text-slate-500 cursor-not-allowed' : 'text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300'"
                                :title="!autorise && !agent.actif ? 'Votre abonnement actuel ne permet pas d\'activer l\'agent.' : ''"
                                @click="basculerStatut"
                            >
                                {{ agent.actif ? 'Désactiver l\'agent' : 'Activer l\'agent' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div v-for="stat in statistiques" :key="stat.libelle">
                            <div class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ stat.valeur }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ stat.libelle }}</div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                        Ces statistiques s'activeront avec la connexion WhatsApp et le moteur de conversation, à venir dans une prochaine mise à jour.
                    </p>
                </div>

                <!-- Connexion WhatsApp -->
                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Connexion WhatsApp</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Connectez le numéro WhatsApp de cette boutique pour que l'agent puisse échanger avec vos clients.
                    </p>

                    <template v-if="!connecteurConfigure">
                        <SecondaryButton disabled class="opacity-60 cursor-not-allowed">
                            Connecter WhatsApp (bientôt disponible)
                        </SecondaryButton>
                    </template>

                    <template v-else-if="connexionStatut === 'connecte'">
                        <p class="text-sm font-medium text-green-700 dark:text-green-400 mb-3">
                            ✅ Connecté{{ numeroConnecte ? ` : ${numeroConnecte}` : '' }}
                        </p>
                        <SecondaryButton @click="deconnecterWhatsapp">Déconnecter</SecondaryButton>
                    </template>

                    <template v-else-if="connexionStatut === 'qr'">
                        <p class="text-sm text-slate-600 dark:text-slate-300 mb-3">
                            Scannez ce QR code depuis WhatsApp sur votre téléphone (Paramètres → Appareils connectés → Connecter un appareil).
                        </p>
                        <img v-if="qrDataUri" :src="qrDataUri" alt="QR code de connexion WhatsApp" class="w-48 h-48 border border-slate-200 dark:border-slate-700 rounded-lg">
                    </template>

                    <template v-else-if="connexionStatut === 'connexion'">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Connexion en cours...</p>
                    </template>

                    <template v-else>
                        <SecondaryButton :disabled="connexionEnCours" @click="connecterWhatsapp">
                            {{ connexionEnCours ? 'Connexion...' : 'Connecter WhatsApp' }}
                        </SecondaryButton>
                        <p v-if="connexionStatut === 'indisponible'" class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                            Service de connexion momentanément indisponible — réessayez dans quelques instants.
                        </p>
                    </template>
                </div>

                <!-- Configuration -->
                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">⚙️ Configuration de l'agent</h3>

                    <form class="space-y-6" @submit.prevent="enregistrer">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="nom" value="Nom de l'agent" />
                                <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                                <InputError :message="form.errors.nom" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="langue" value="Langue principale" />
                                <SelectInput id="langue" v-model="form.langue" class="mt-1 block w-full">
                                    <option value="fr">Français</option>
                                    <option value="en">Anglais</option>
                                </SelectInput>
                                <InputError :message="form.errors.langue" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="personnalite" value="Personnalité" />
                                <TextInput id="personnalite" v-model="form.personnalite" type="text" class="mt-1 block w-full" placeholder="Chaleureuse, professionnelle..." />
                                <InputError :message="form.errors.personnalite" class="mt-2" />
                            </div>

                            <div>
                                <InputLabel for="ton" value="Ton" />
                                <TextInput id="ton" v-model="form.ton" type="text" class="mt-1 block w-full" placeholder="Amical, formel..." />
                                <InputError :message="form.errors.ton" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="message_accueil" value="Message d'accueil" />
                            <textarea id="message_accueil" v-model="form.message_accueil" rows="2" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                            <InputError :message="form.errors.message_accueil" class="mt-2" />
                        </div>

                        <div>
                            <InputLabel for="message_hors_horaires" value="Message hors horaires" />
                            <textarea id="message_hors_horaires" v-model="form.message_hors_horaires" rows="2" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                            <InputError :message="form.errors.message_hors_horaires" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                        </div>
                    </form>
                </div>

                <!-- Conversations -->
                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg">
                    <EmptyState
                        titre="Aucune conversation pour le moment"
                        description="Les conversations gérées par votre agent apparaîtront ici."
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
