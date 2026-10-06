<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import RelanceWhatsappModal from './Partials/RelanceWhatsappModal.vue';
import RelanceMassiveModal from './Partials/RelanceMassiveModal.vue';

const props = defineProps({
    utilisateurs: Object,
    filtres: Object,
    permissionsWhatsapp: Object,
    permissionsNotifications: Object,
    modelesWhatsapp: { type: Array, default: () => [] },
});

const recherche = ref(props.filtres.recherche ?? '');
const statut = ref(props.filtres.statut ?? '');
const avecWhatsapp = ref(props.filtres.avecWhatsapp ?? '');
const avecBoutique = ref(props.filtres.avecBoutique ?? '');
const avecProduit = ref(props.filtres.avecProduit ?? '');

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch([statut, avecWhatsapp, avecBoutique, avecProduit], appliquerFiltres);

function appliquerFiltres() {
    router.get(route('admin.utilisateurs.index'), {
        recherche: recherche.value,
        statut: statut.value,
        avecWhatsapp: avecWhatsapp.value,
        avecBoutique: avecBoutique.value,
        avecProduit: avecProduit.value,
    }, { preserveState: true, replace: true });
}

const utilisateurARelancer = ref(null);
const cibleRelance = computed(() => (utilisateurARelancer.value
    ? { id: utilisateurARelancer.value.id, nom: utilisateurARelancer.value.name, whatsapp: utilisateurARelancer.value.whatsapp, plan: utilisateurARelancer.value.plan }
    : null));

// Sélection multiple pour la relance groupée -- limitée à la page affichée (pas de
// sélection "toutes les pages", pour rester simple et toujours cohérente avec ce que
// l'admin voit réellement à l'écran).
const selectionnes = ref([]);
const tousSelectionnesSurPage = computed(() => props.utilisateurs.data.length > 0 && props.utilisateurs.data.every((u) => selectionnes.value.includes(u.id)));
const basculerToutSurPage = () => {
    selectionnes.value = tousSelectionnesSurPage.value
        ? selectionnes.value.filter((id) => !props.utilisateurs.data.some((u) => u.id === id))
        : [...new Set([...selectionnes.value, ...props.utilisateurs.data.map((u) => u.id)])];
};
const relanceMassiveOuverte = ref(false);
const apresEnvoiMassif = () => {
    relanceMassiveOuverte.value = false;
    selectionnes.value = [];
};

const basculer = (utilisateur) => {
    const action = utilisateur.est_actif ? 'désactiver' : 'réactiver';
    if (confirm(`Confirmer : ${action} "${utilisateur.name}" ?`)) {
        router.patch(route('admin.utilisateurs.basculer', utilisateur.id), {}, { preserveScroll: true });
    }
};

const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR');
</script>

<template>
    <AppLayout title="Administration — Utilisateurs">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Gestion des utilisateurs</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <AdminSubNav />

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4 flex flex-col sm:flex-row sm:flex-wrap gap-4">
                    <TextInput v-model="recherche" placeholder="Rechercher par nom, email, WhatsApp ou téléphone..." class="flex-1" />
                    <SelectInput v-model="statut" class="sm:w-48">
                        <option value="">Tous les statuts</option>
                        <option value="actif">Actif</option>
                        <option value="inactif">Inactif</option>
                    </SelectInput>
                    <SelectInput v-if="permissionsWhatsapp?.voir" v-model="avecWhatsapp" class="sm:w-56">
                        <option value="">Tous (WhatsApp)</option>
                        <option value="1">Avec WhatsApp</option>
                        <option value="0">Sans WhatsApp</option>
                    </SelectInput>
                    <SelectInput v-model="avecBoutique" class="sm:w-48">
                        <option value="">Tous (boutique)</option>
                        <option value="1">Avec boutique</option>
                        <option value="0">Sans boutique</option>
                    </SelectInput>
                    <SelectInput v-model="avecProduit" class="sm:w-48">
                        <option value="">Tous (produits)</option>
                        <option value="1">Avec produits</option>
                        <option value="0">Sans produit</option>
                    </SelectInput>
                </div>

                <div v-if="permissionsNotifications?.envoyer && selectionnes.length > 0" class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-3 flex items-center justify-between">
                    <span class="text-sm text-blue-800 dark:text-blue-300">{{ selectionnes.length }} utilisateur{{ selectionnes.length > 1 ? 's' : '' }} sélectionné{{ selectionnes.length > 1 ? 's' : '' }}</span>
                    <div class="flex items-center gap-3">
                        <button type="button" class="text-sm text-blue-700 dark:text-blue-300 hover:underline" @click="selectionnes = []">Désélectionner</button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700"
                            @click="relanceMassiveOuverte = true"
                        >
                            💬 Relancer la sélection
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th v-if="permissionsNotifications?.envoyer" class="px-4 py-3 text-left">
                                    <input type="checkbox" :checked="tousSelectionnesSurPage" class="rounded border-slate-300 dark:border-slate-600" @change="basculerToutSurPage" />
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Email</th>
                                <th v-if="permissionsWhatsapp?.voir" class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">WhatsApp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Boutiques</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Inscrit le</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="utilisateurs.data.length === 0">
                                <td :colspan="(permissionsWhatsapp?.voir ? 8 : 7) + (permissionsNotifications?.envoyer ? 1 : 0)" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun utilisateur trouvé.</td>
                            </tr>
                            <tr v-for="utilisateur in utilisateurs.data" :key="utilisateur.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td v-if="permissionsNotifications?.envoyer" class="px-4 py-4">
                                    <input type="checkbox" :value="utilisateur.id" v-model="selectionnes" class="rounded border-slate-300 dark:border-slate-600" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ utilisateur.name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ utilisateur.email }}</td>
                                <td v-if="permissionsWhatsapp?.voir" class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ utilisateur.whatsapp ?? 'Non renseigné' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ utilisateur.plan ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ utilisateur.boutiques_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="utilisateur.est_actif ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300'">
                                        {{ utilisateur.est_actif ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(utilisateur.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('admin.utilisateurs.show', utilisateur.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Voir</Link>
                                    <button v-if="permissionsWhatsapp?.contacter || permissionsNotifications?.envoyer" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300" @click="utilisateurARelancer = utilisateur">
                                        Relancer
                                    </button>
                                    <button class="text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-200" @click="basculer(utilisateur)">
                                        {{ utilisateur.est_actif ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <RelanceWhatsappModal
                    :show="utilisateurARelancer !== null"
                    :cible="cibleRelance"
                    type="utilisateur"
                    :modeles="modelesWhatsapp"
                    :peut-envoyer-message="permissionsNotifications?.envoyer"
                    @close="utilisateurARelancer = null"
                    @envoye="utilisateurARelancer = null"
                />

                <RelanceMassiveModal
                    :show="relanceMassiveOuverte"
                    :utilisateur-ids="selectionnes"
                    :nombre-selectionnes="selectionnes.length"
                    @close="relanceMassiveOuverte = false"
                    @envoye="apresEnvoiMassif"
                />

                <div v-if="utilisateurs.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in utilisateurs.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                        :disabled="!link.url"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
