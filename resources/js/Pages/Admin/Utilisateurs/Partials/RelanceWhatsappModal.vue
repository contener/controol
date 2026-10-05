<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Composant partagé entre la relance d'un utilisateur CONTROOL (Utilisateurs/Index|Show.vue,
// Notifications/Index.vue) et celle d'un contact de prospection (Contacts/Index|Show.vue) —
// un seul historique, un seul générateur de lien wa.me côté serveur (voir
// App\Services\WhatsappRelanceService). Le canal "message interne" (cloche de
// notification) n'existe que pour un vrai utilisateur CONTROOL -- un contact de
// prospection n'a pas de compte, donc pas de cloche à notifier.
const props = defineProps({
    show: Boolean,
    // { id, nom, whatsapp (nullable si non autorisé/absent), plan?, entreprise?, ville?,
    //   joursRestants? (essai en cours uniquement) }
    cible: { type: Object, default: null },
    type: { type: String, default: 'utilisateur' }, // 'utilisateur' | 'contact'
    modeles: { type: Array, default: () => [] },
    // Présélectionne un modèle à l'ouverture (ex. relance essai : le bon modèle est déjà
    // déterminé selon que l'utilisateur a une boutique ou non) -- laisse quand même la main
    // à l'admin pour changer de modèle ou éditer librement le texte avant l'envoi.
    modeleCleInitiale: { type: String, default: '' },
    // Autorise (ou non) le deuxième canal "message interne" -- jamais affiché pour un
    // contact de prospection, et masqué si l'admin n'a pas la permission notifications.envoyer.
    peutEnvoyerMessage: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'envoye']);

const modeleCle = ref('');
const message = ref('');
const enCours = ref(false);
const erreur = ref('');
const canal = ref('whatsapp'); // 'whatsapp' | 'message'

const canalMessageDisponible = computed(() => props.type === 'utilisateur' && props.peutEnvoyerMessage);

const routeContacter = computed(() => props.type === 'contact'
    ? route('admin.contacts.whatsapp.contacter', props.cible.id)
    : route('admin.utilisateurs.whatsapp.contacter', props.cible.id));

const substituer = (texte) => texte
    .replaceAll('{{nom}}', props.cible?.nom ?? '')
    .replaceAll('{{prenom}}', props.cible?.prenom ?? props.cible?.nom ?? '')
    .replaceAll('{{plan}}', props.cible?.plan ?? '')
    .replaceAll('{{entreprise}}', props.cible?.entreprise ?? '')
    .replaceAll('{{ville}}', props.cible?.ville ?? '')
    .replaceAll('{{boutique}}', props.cible?.entreprise ?? '')
    .replaceAll('{{date_expiration}}', props.cible?.dateExpiration ?? '')
    .replaceAll('{{jours_restants}}', props.cible?.joursRestants !== undefined && props.cible?.joursRestants !== null ? String(props.cible.joursRestants) : '')
    .replaceAll('{{lien_inscription}}', route('register'));

watch(() => props.show, (visible) => {
    if (!visible) {
        return;
    }

    erreur.value = '';
    // Si l'utilisateur n'a renseigné aucun numéro WhatsApp, autant proposer directement
    // le canal qui fonctionnera -- l'admin reste toujours libre de basculer sur WhatsApp
    // (il obtiendra alors l'erreur serveur habituelle s'il n'y a vraiment aucun numéro).
    canal.value = (!props.cible?.whatsapp && canalMessageDisponible.value) ? 'message' : 'whatsapp';
    const modeleInitial = props.modeles.find((m) => m.cle === props.modeleCleInitiale);
    modeleCle.value = modeleInitial ? modeleInitial.cle : '';
    message.value = modeleInitial ? substituer(modeleInitial.texte) : '';
});

watch(modeleCle, (cle) => {
    const modele = props.modeles.find((m) => m.cle === cle);
    if (modele) {
        message.value = substituer(modele.texte);
    }
});

// N'utilise pas router.post() (Inertia) exprès : on ne veut pas naviguer avant l'ouverture
// du nouvel onglet WhatsApp — un simple appel axios qui renvoie le lien déjà construit.
const ouvrirWhatsapp = async () => {
    enCours.value = true;
    erreur.value = '';

    try {
        const { data } = await axios.post(routeContacter.value, {
            message: message.value,
            modele_cle: modeleCle.value || null,
        });
        window.open(data.lien, '_blank');
        emit('envoye');
    } catch (e) {
        erreur.value = e.response?.data?.message ?? "Impossible d'ouvrir WhatsApp pour le moment.";
    } finally {
        enCours.value = false;
    }
};

// Le message interne, lui, est réellement envoyé côté serveur (déposé sur la cloche de
// l'utilisateur) -- pas juste "préparé" comme pour WhatsApp.
const envoyerMessageInterne = () => {
    enCours.value = true;
    erreur.value = '';

    router.post(route('admin.utilisateurs.message.contacter', props.cible.id), { message: message.value }, {
        preserveScroll: true,
        onSuccess: () => emit('envoye'),
        onError: (errors) => { erreur.value = errors.message ?? "Impossible d'envoyer le message pour le moment."; },
        onFinish: () => { enCours.value = false; },
    });
};

const envoyer = () => {
    if (!message.value.trim()) {
        erreur.value = 'Le message ne peut pas être vide.';
        return;
    }

    if (canal.value === 'whatsapp') {
        ouvrirWhatsapp();
    } else {
        envoyerMessageInterne();
    }
};

const texteBouton = computed(() => (canal.value === 'whatsapp' ? 'Ouvrir WhatsApp' : '💬 Envoyer le message'));
const texteAide = computed(() => (canal.value === 'whatsapp'
    ? "WhatsApp s'ouvrira dans un nouvel onglet avec ce message prérempli — vous devrez l'envoyer vous-même depuis WhatsApp."
    : "Le message sera déposé immédiatement sur la cloche de notification de l'utilisateur, au nom de l'équipe technique de Controol."));
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>{{ type === 'contact' ? 'Relancer ce contact' : "Relancer l'utilisateur" }}</template>
        <template #content>
            <p class="text-sm text-slate-700 dark:text-slate-300">Destinataire : <strong>{{ cible?.nom }}</strong></p>
            <p v-if="cible?.whatsapp" class="text-sm text-slate-500 dark:text-slate-400">WhatsApp : {{ cible.whatsapp }}</p>

            <div v-if="canalMessageDisponible" class="mt-3 flex gap-2">
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md border"
                    :class="canal === 'whatsapp' ? 'bg-green-600 text-white border-green-600' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'"
                    @click="canal = 'whatsapp'"
                >
                    📱 WhatsApp
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 text-xs font-medium rounded-md border"
                    :class="canal === 'message' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'"
                    @click="canal = 'message'"
                >
                    💬 Message (cloche)
                </button>
            </div>

            <div v-if="modeles.length > 0" class="mt-4">
                <InputLabel for="modele_cle" value="Modèle de message (optionnel)" />
                <SelectInput id="modele_cle" v-model="modeleCle" class="mt-1 block w-full">
                    <option value="">Message personnalisé</option>
                    <option v-for="modele in modeles" :key="modele.cle" :value="modele.cle">{{ modele.libelle }}</option>
                </SelectInput>
            </div>

            <div class="mt-4">
                <InputLabel for="message" value="Message" />
                <textarea id="message" v-model="message" rows="5" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                <InputError :message="erreur" class="mt-2" />
            </div>

            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">{{ texteAide }}</p>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="envoyer">
                {{ texteBouton }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
