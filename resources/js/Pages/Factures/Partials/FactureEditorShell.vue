<script setup>
import { computed, ref } from 'vue';
import FactureForm from './FactureForm.vue';
import InvoicePreview from '@/Components/InvoicePreview.vue';
import TemplatePickerCard from '@/Components/TemplatePickerCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useFactureApercu } from '@/Composables/useFactureApercu';

const props = defineProps({
    form: Object,
    processing: Boolean,
    clients: Array,
    produits: Array,
    boutique: Object,
    modeles: Array,
    tauxTvaDefaut: {
        type: Number,
        default: 0,
    },
    numeroPrevisualise: {
        type: String,
        default: '(brouillon)',
    },
});

const emit = defineEmits(['submit']);

const apercu = useFactureApercu(props.form, {
    clients: () => props.clients,
    boutique: () => props.boutique,
    numeroPrevisualise: props.numeroPrevisualise,
});

const ongletMobile = ref('saisie');
const pickerOuvert = ref(false);
const pleinEcranOuvert = ref(false);

const modeleActuel = computed(() => props.modeles.find((m) => m.id === props.form.modele_id));

const choisirModele = (id) => {
    props.form.modele_id = id;
    pickerOuvert.value = false;
};

const enregistrerBrouillon = () => {
    props.form.statut = 'brouillon';
    emit('submit');
};

const enregistrer = () => {
    if (props.form.statut === 'brouillon') {
        props.form.statut = 'envoyee';
    }
    emit('submit');
};

const imprimer = () => {
    pleinEcranOuvert.value = true;
    requestAnimationFrame(() => {
        window.print();
    });
};
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <span>Modèle : <strong class="text-slate-900 dark:text-slate-100">{{ modeleActuel?.label ?? '—' }}</strong></span>
                <SecondaryButton type="button" @click="pickerOuvert = true">Changer de modèle</SecondaryButton>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <SecondaryButton type="button" @click="pleinEcranOuvert = true">Aperçu plein écran</SecondaryButton>
                <SecondaryButton type="button" @click="imprimer">Imprimer</SecondaryButton>
                <SecondaryButton type="button" :disabled="processing" @click="enregistrerBrouillon">Enregistrer comme brouillon</SecondaryButton>
                <PrimaryButton type="button" :disabled="processing || form.lignes.length === 0" @click="enregistrer">Enregistrer</PrimaryButton>
            </div>
        </div>

        <!-- Onglets mobile -->
        <div class="flex lg:hidden mb-4 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
            <button
                type="button"
                class="flex-1 py-2 text-sm font-medium"
                :class="ongletMobile === 'saisie' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                @click="ongletMobile = 'saisie'"
            >
                Saisie
            </button>
            <button
                type="button"
                class="flex-1 py-2 text-sm font-medium"
                :class="ongletMobile === 'apercu' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300'"
                @click="ongletMobile = 'apercu'"
            >
                Aperçu
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6" :class="ongletMobile === 'apercu' ? 'hidden lg:block' : ''">
                <FactureForm :form="form" :clients="clients" :produits="produits" :boutique="boutique" :taux-tva-defaut="tauxTvaDefaut" />
            </div>

            <div class="lg:sticky lg:top-4 self-start" :class="ongletMobile === 'saisie' ? 'hidden lg:block' : ''">
                <div class="bg-slate-100 dark:bg-slate-950 rounded-lg p-4 shadow-sm max-h-[80vh] overflow-y-auto">
                    <InvoicePreview :modele-id="form.modele_id" :apercu="apercu" />
                </div>
            </div>
        </div>

        <!-- Sélecteur de modèle -->
        <div v-if="pickerOuvert" class="fixed inset-0 z-40 bg-slate-900/60 flex items-start justify-center p-4 overflow-y-auto" @click.self="pickerOuvert = false">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl max-w-6xl w-full mt-8 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Choisir un modèle</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" @click="pickerOuvert = false">✕</button>
                </div>
                <TemplatePickerCard :modeles="modeles" :selectionne="form.modele_id" :apercu-exemple="apercu" @select="choisirModele" />
            </div>
        </div>

        <!-- Aperçu plein écran / zone d'impression -->
        <div v-if="pleinEcranOuvert" class="fixed inset-0 z-50 bg-slate-900/80 flex items-start justify-center p-4 overflow-y-auto print:p-0 print:bg-white">
            <div class="print:hidden absolute top-4 right-4">
                <SecondaryButton type="button" @click="pleinEcranOuvert = false">Fermer ✕</SecondaryButton>
            </div>
            <div class="print-area bg-white rounded-lg shadow-xl max-w-3xl w-full mt-8 print:mt-0 print:max-w-none print:shadow-none print:rounded-none">
                <InvoicePreview :modele-id="form.modele_id" :apercu="apercu" />
            </div>
        </div>
    </div>
</template>
