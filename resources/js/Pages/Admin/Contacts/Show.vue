<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import RelanceWhatsappModal from '../Utilisateurs/Partials/RelanceWhatsappModal.vue';

const props = defineProps({
    contact: Object,
    logsWhatsapp: Array,
    statutsWhatsapp: Object,
    statutsCommerciaux: Object,
    modelesWhatsapp: { type: Array, default: () => [] },
    permissionsContacts: Object,
});

const relanceOuverte = ref(false);
const cibleRelance = computed(() => ({ id: props.contact.id, nom: props.contact.nom, prenom: props.contact.prenom, whatsapp: props.contact.whatsapp || props.contact.telephone, entreprise: props.contact.entreprise, ville: props.contact.ville }));

const changerStatutCommercial = (statut) => {
    router.patch(route('admin.contacts.statut-commercial', props.contact.id), { statut_commercial: statut }, { preserveScroll: true });
};
const changerStatutWhatsapp = (statut) => {
    router.patch(route('admin.contacts.statut-whatsapp', props.contact.id), { statut_whatsapp: statut }, { preserveScroll: true });
};

const confirmerEnvoi = (log) => {
    router.patch(route('admin.utilisateurs.whatsapp.confirmer', log.id), {}, { preserveScroll: true });
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('fr-FR') : '—');
const formatDateHeure = (d) => new Date(d).toLocaleString('fr-FR');
</script>

<template>
    <AppLayout :title="`Contact — ${contact.nom ?? contact.whatsapp}`">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ contact.nom ?? 'Contact sans nom' }}</h2>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Téléphone</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.telephone ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">WhatsApp</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.whatsapp ?? 'Non renseigné' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">E-mail</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.email ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Ville</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.ville ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Entreprise</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.entreprise ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Poste</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.poste ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Adresse</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.adresse ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Région</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.region ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Pays</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.pays ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Code postal</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.code_postal ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Anniversaire</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.date_anniversaire ? formatDate(contact.date_anniversaire) : '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Source</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ contact.source ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Importé le</div>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ formatDate(contact.created_at) }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Compte CONTROOL</div>
                            <div class="mt-1">
                                <Link v-if="contact.utilisateur" :href="route('admin.utilisateurs.show', contact.utilisateur.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ contact.utilisateur.name }}
                                </Link>
                                <span v-else class="text-gray-400 dark:text-gray-500">Non inscrit</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="contact.telephones_secondaires?.length || contact.emails_secondaires?.length" class="mt-4 border-t border-gray-100 dark:border-gray-700 pt-4 text-sm space-y-1">
                        <p v-if="contact.telephones_secondaires?.length" class="text-gray-600 dark:text-gray-300">
                            Autres numéros : {{ contact.telephones_secondaires.join(', ') }}
                        </p>
                        <p v-if="contact.emails_secondaires?.length" class="text-gray-600 dark:text-gray-300">
                            Autres e-mails : {{ contact.emails_secondaires.join(', ') }}
                        </p>
                    </div>

                    <p v-if="contact.notes" class="mt-4 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line border-t border-gray-100 dark:border-gray-700 pt-4">{{ contact.notes }}</p>

                    <div v-if="permissionsContacts?.whatsapp_contacter && (contact.whatsapp || contact.telephone)" class="mt-4">
                        <PrimaryButton @click="relanceOuverte = true">Relancer sur WhatsApp</PrimaryButton>
                    </div>
                </div>

                <div v-if="permissionsContacts?.modifier" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <InputLabel value="Statut commercial" />
                        <SelectInput :model-value="contact.statut_commercial" class="mt-1 block w-full" @update:model-value="changerStatutCommercial">
                            <option v-for="(libelle, cle) in statutsCommerciaux" :key="cle" :value="cle">{{ libelle }}</option>
                        </SelectInput>
                    </div>
                    <div class="flex-1">
                        <InputLabel value="Statut WhatsApp" />
                        <SelectInput :model-value="contact.statut_whatsapp" class="mt-1 block w-full" @update:model-value="changerStatutWhatsapp">
                            <option v-for="(libelle, cle) in statutsWhatsapp" :key="cle" :value="cle">{{ libelle }}</option>
                        </SelectInput>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Historique des relances WhatsApp</h3>
                    <div v-if="logsWhatsapp.length === 0" class="text-sm text-gray-400 dark:text-gray-500">Aucune relance pour le moment.</div>
                    <ul v-else class="space-y-3 text-sm">
                        <li v-for="log in logsWhatsapp" :key="log.id" class="border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-gray-500 dark:text-gray-400">Ouvert par {{ log.admin?.name ?? '—' }} le {{ formatDateHeure(log.ouvert_a) }}</span>
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="log.confirme ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                                    {{ log.confirme ? 'Confirmé' : 'Non confirmé' }}
                                </span>
                            </div>
                            <p class="mt-1 text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ log.message }}</p>
                            <button v-if="!log.confirme" class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline" @click="confirmerEnvoi(log)">Marquer comme envoyé</button>
                        </li>
                    </ul>
                </div>

                <RelanceWhatsappModal
                    :show="relanceOuverte"
                    :cible="cibleRelance"
                    type="contact"
                    :modeles="modelesWhatsapp"
                    @close="relanceOuverte = false"
                    @envoye="relanceOuverte = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
