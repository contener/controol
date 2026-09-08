<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Composant partagé entre la relance d'un utilisateur CONTROOL (Utilisateurs/Index|Show.vue)
// et celle d'un contact de prospection (Contacts/Index|Show.vue) — un seul historique, un
// seul générateur de lien wa.me côté serveur (voir App\Services\WhatsappRelanceService).
const props = defineProps({
    show: Boolean,
    cible: { type: Object, default: null }, // { id, nom, whatsapp (nullable si non autorisé/absent), plan?, entreprise?, ville? }
    type: { type: String, default: 'utilisateur' }, // 'utilisateur' | 'contact'
    modeles: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'envoye']);

const modeleCle = ref('');
const message = ref('');
const enCours = ref(false);
const erreur = ref('');

const routeContacter = computed(() => props.type === 'contact'
    ? route('admin.contacts.whatsapp.contacter', props.cible.id)
    : route('admin.utilisateurs.whatsapp.contacter', props.cible.id));

const substituer = (texte) => texte
    .replaceAll('{{nom}}', props.cible?.nom ?? '')
    .replaceAll('{{prenom}}', props.cible?.nom ?? '')
    .replaceAll('{{plan}}', props.cible?.plan ?? '')
    .replaceAll('{{entreprise}}', props.cible?.entreprise ?? '')
    .replaceAll('{{ville}}', props.cible?.ville ?? '')
    .replaceAll('{{boutique}}', props.cible?.entreprise ?? '')
    .replaceAll('{{date_expiration}}', '')
    .replaceAll('{{lien_inscription}}', route('register'));

watch(() => props.show, (visible) => {
    if (visible) {
        modeleCle.value = '';
        message.value = '';
        erreur.value = '';
    }
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
    if (!message.value.trim()) {
        erreur.value = 'Le message ne peut pas être vide.';
        return;
    }

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
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>{{ type === 'contact' ? 'Relancer ce contact' : "Relancer l'utilisateur" }}</template>
        <template #content>
            <p class="text-sm text-gray-700 dark:text-gray-300">Destinataire : <strong>{{ cible?.nom }}</strong></p>
            <p v-if="cible?.whatsapp" class="text-sm text-gray-500 dark:text-gray-400">WhatsApp : {{ cible.whatsapp }}</p>

            <div v-if="modeles.length > 0" class="mt-4">
                <InputLabel for="modele_cle" value="Modèle de message (optionnel)" />
                <SelectInput id="modele_cle" v-model="modeleCle" class="mt-1 block w-full">
                    <option value="">Message personnalisé</option>
                    <option v-for="modele in modeles" :key="modele.cle" :value="modele.cle">{{ modele.libelle }}</option>
                </SelectInput>
            </div>

            <div class="mt-4">
                <InputLabel for="message" value="Message" />
                <textarea id="message" v-model="message" rows="5" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                <InputError :message="erreur" class="mt-2" />
            </div>

            <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                WhatsApp s'ouvrira dans un nouvel onglet avec ce message prérempli — vous devrez l'envoyer vous-même depuis WhatsApp.
            </p>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="ouvrirWhatsapp">
                Ouvrir WhatsApp
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
