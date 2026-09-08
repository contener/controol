<script setup>
import { ref, watch, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import RelanceWhatsappModal from '../Utilisateurs/Partials/RelanceWhatsappModal.vue';
import NouveauContactModal from './Partials/NouveauContactModal.vue';

const props = defineProps({
    contacts: Object,
    filtres: Object,
    statistiques: Object,
    statutsWhatsapp: Object,
    statutsCommerciaux: Object,
    modelesWhatsapp: { type: Array, default: () => [] },
    permissionsContacts: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const statutWhatsapp = ref(props.filtres.statutWhatsapp ?? '');
const statutCommercial = ref(props.filtres.statutCommercial ?? '');
const compteLie = ref(props.filtres.compteLie ?? '');
const jamaisRelance = ref(props.filtres.jamaisRelance === '1' || props.filtres.jamaisRelance === true);

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch([statutWhatsapp, statutCommercial, compteLie, jamaisRelance], appliquerFiltres);

function appliquerFiltres() {
    router.get(route('admin.contacts.index'), {
        recherche: recherche.value,
        statutWhatsapp: statutWhatsapp.value,
        statutCommercial: statutCommercial.value,
        compteLie: compteLie.value,
        jamaisRelance: jamaisRelance.value ? '1' : '',
    }, { preserveState: true, replace: true });
}

const filtreWhatsappSansCompte = () => {
    statutWhatsapp.value = 'sur_whatsapp';
    compteLie.value = '0';
};

const selection = ref([]);
const toutSelectionner = (e) => {
    selection.value = e.target.checked ? props.contacts.data.map((c) => c.id) : [];
};

const contactARelancer = ref(null);
const cibleRelance = computed(() => (contactARelancer.value
    ? { id: contactARelancer.value.id, nom: contactARelancer.value.nom, prenom: contactARelancer.value.prenom, whatsapp: contactARelancer.value.whatsapp, entreprise: contactARelancer.value.entreprise, ville: contactARelancer.value.ville }
    : null));

const nouveauContactOuvert = ref(false);

const supprimer = (contact) => {
    if (confirm(`Supprimer le contact "${contact.nom ?? contact.whatsapp}" ?`)) {
        router.delete(route('admin.contacts.destroy', contact.id), { preserveScroll: true });
    }
};

const changerStatutSelection = (statut) => {
    if (!statut || selection.value.length === 0) return;
    router.patch(route('admin.contacts.statut-commercial-groupe'), { ids: selection.value, statut_commercial: statut }, {
        preserveScroll: true,
        onSuccess: () => { selection.value = []; },
    });
};

const lienExportSelection = computed(() => route('admin.contacts.export', { ids: selection.value.join(',') }));
const lienExportFiltre = computed(() => route('admin.contacts.export', {
    recherche: recherche.value, statutWhatsapp: statutWhatsapp.value, statutCommercial: statutCommercial.value, compteLie: compteLie.value,
}));

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('fr-FR') : '—');
</script>

<template>
    <AppLayout title="Administration — Contacts">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Contacts</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Total contacts</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Contacts WhatsApp</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.sur_whatsapp }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Comptes CONTROOL</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.comptes_lies }}</div>
                    </div>
                    <button type="button" class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 text-left hover:ring-2 hover:ring-blue-500" @click="filtreWhatsappSansCompte">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">WhatsApp sans compte</div>
                        <div class="mt-1 text-2xl font-semibold text-blue-600 dark:text-blue-400">{{ statistiques.whatsapp_sans_compte }}</div>
                    </button>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between">
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="permissionsContacts?.importer" :href="route('admin.contacts.import.index')">
                            <SecondaryButton>Importer Excel</SecondaryButton>
                        </Link>
                        <a v-if="permissionsContacts?.exporter" :href="lienExportFiltre">
                            <SecondaryButton>Exporter Excel</SecondaryButton>
                        </a>
                        <PrimaryButton v-if="permissionsContacts?.modifier" @click="nouveauContactOuvert = true">Nouveau contact</PrimaryButton>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-3 flex-wrap">
                    <TextInput v-model="recherche" placeholder="Rechercher (nom, téléphone, WhatsApp, email)..." class="flex-1 min-w-[220px]" />
                    <SelectInput v-model="statutWhatsapp" class="sm:w-48">
                        <option value="">Tous (WhatsApp)</option>
                        <option v-for="(libelle, cle) in statutsWhatsapp" :key="cle" :value="cle">{{ libelle }}</option>
                    </SelectInput>
                    <SelectInput v-model="compteLie" class="sm:w-48">
                        <option value="">Tous (compte)</option>
                        <option value="1">Compte créé</option>
                        <option value="0">Compte non créé</option>
                    </SelectInput>
                    <SelectInput v-model="statutCommercial" class="sm:w-48">
                        <option value="">Tous (statut)</option>
                        <option v-for="(libelle, cle) in statutsCommerciaux" :key="cle" :value="cle">{{ libelle }}</option>
                    </SelectInput>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="jamaisRelance" type="checkbox" class="rounded border-slate-300">
                        Jamais relancé
                    </label>
                </div>

                <div v-if="selection.length > 0" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-blue-800 dark:text-blue-300">{{ selection.length }} sélectionné(s)</span>
                    <SelectInput v-if="permissionsContacts?.modifier" class="sm:w-56" @change="(e) => changerStatutSelection(e.target.value)">
                        <option value="">Changer le statut commercial...</option>
                        <option v-for="(libelle, cle) in statutsCommerciaux" :key="cle" :value="cle">{{ libelle }}</option>
                    </SelectInput>
                    <a v-if="permissionsContacts?.exporter" :href="lienExportSelection" class="text-blue-700 dark:text-blue-300 underline">Exporter la sélection</a>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-4 py-3"><input type="checkbox" class="rounded border-slate-300" @change="toutSelectionner" /></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">WhatsApp</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Compte</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Dernière relance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="contacts.data.length === 0">
                                <td colspan="7" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">Aucun contact trouvé.</td>
                            </tr>
                            <tr v-for="contact in contacts.data" :key="contact.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-4 py-4"><input v-model="selection" type="checkbox" :value="contact.id" class="rounded border-slate-300" /></td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ contact.nom ?? '—' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ contact.whatsapp ?? contact.telephone ?? 'Non renseigné' }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm">
                                    <Link v-if="contact.utilisateur" :href="route('admin.utilisateurs.show', contact.utilisateur.id)" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        {{ contact.utilisateur.name }}
                                    </Link>
                                    <span v-else class="text-slate-400 dark:text-slate-500">Non inscrit</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ statutsCommerciaux[contact.statut_commercial] ?? contact.statut_commercial }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(contact.dernier_contact_a) }}</td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('admin.contacts.show', contact.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Voir</Link>
                                    <button v-if="permissionsContacts?.whatsapp_contacter && (contact.whatsapp || contact.telephone)" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300" @click="contactARelancer = contact">
                                        WhatsApp
                                    </button>
                                    <button v-if="permissionsContacts?.supprimer" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300" @click="supprimer(contact)">
                                        Supprimer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="contacts.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in contacts.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                        :disabled="!link.url"
                    />
                </div>

                <RelanceWhatsappModal
                    :show="contactARelancer !== null"
                    :cible="cibleRelance"
                    type="contact"
                    :modeles="modelesWhatsapp"
                    @close="contactARelancer = null"
                    @envoye="contactARelancer = null"
                />

                <NouveauContactModal :show="nouveauContactOuvert" @close="nouveauContactOuvert = false" />
            </div>
        </div>
    </AppLayout>
</template>
