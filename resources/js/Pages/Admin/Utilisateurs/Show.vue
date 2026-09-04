<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    utilisateur: Object,
    boutiques: Array,
    audits: Array,
});

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('fr-FR') : '—');
const formatDateHeure = (d) => new Date(d).toLocaleString('fr-FR');

const basculer = () => {
    const action = props.utilisateur.est_actif ? 'désactiver' : 'réactiver';
    if (confirm(`Confirmer : ${action} "${props.utilisateur.name}" ? ${props.utilisateur.est_actif ? 'Il sera immédiatement déconnecté.' : ''}`)) {
        router.patch(route('admin.utilisateurs.basculer', props.utilisateur.id), {}, { preserveScroll: true });
    }
};

const supprimerBoutique = (boutique) => {
    if (confirm(`Supprimer définitivement la boutique "${boutique.nom}" et toutes ses données (clients, produits, factures) ? Cette action est irréversible.`)) {
        router.delete(route('admin.utilisateurs.boutiques.destroy', [props.utilisateur.id, boutique.id]), { preserveScroll: true });
    }
};

// Confirmation renforcée pour la suppression de compte : action irréversible, on exige
// de retaper l'email exact avant d'activer le bouton, plutôt qu'un simple confirm().
const confirmationEmail = ref('');
const peutSupprimerCompte = computed(() => confirmationEmail.value === props.utilisateur.email);
const supprimerCompte = () => {
    if (!peutSupprimerCompte.value) return;
    if (confirm(`Dernière confirmation : supprimer définitivement le compte de ${props.utilisateur.name} et toutes ses données ?`)) {
        router.delete(route('admin.utilisateurs.destroy', props.utilisateur.id));
    }
};

const actionLabels = {
    utilisateur_desactive: 'Désactivation',
    utilisateur_reactive: 'Réactivation',
    boutique_supprimee: 'Suppression de boutique',
    utilisateur_supprime: 'Suppression du compte',
};
</script>

<template>
    <AppLayout :title="`Utilisateur — ${utilisateur.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ utilisateur.name }}</h2>
                <span class="px-2 py-1 text-xs font-medium rounded-full" :class="utilisateur.est_actif ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                    {{ utilisateur.est_actif ? 'Actif' : 'Inactif' }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Email</div>
                        <div class="mt-1 text-gray-900 dark:text-gray-100">{{ utilisateur.email }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Téléphone</div>
                        <div class="mt-1 text-gray-900 dark:text-gray-100">{{ utilisateur.telephone ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Plan actif</div>
                        <div class="mt-1 text-gray-900 dark:text-gray-100">{{ utilisateur.plan ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Inscrit le</div>
                        <div class="mt-1 text-gray-900 dark:text-gray-100">{{ formatDate(utilisateur.created_at) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Statut du compte</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Un compte désactivé est immédiatement déconnecté et ne peut plus se connecter à l'application.
                        </p>
                    </div>
                    <SecondaryButton @click="basculer">{{ utilisateur.est_actif ? 'Désactiver' : 'Réactiver' }}</SecondaryButton>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                    <div class="p-6 pb-3">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Boutiques ({{ boutiques.length }})</h3>
                    </div>
                    <div v-if="boutiques.length === 0" class="px-6 pb-6 text-sm text-gray-400 dark:text-gray-500">Aucune boutique.</div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="boutique in boutiques" :key="boutique.id" class="px-6 py-4 flex items-center justify-between">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ boutique.nom }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ boutique.clients_count }} client(s) · {{ boutique.produits_count }} produit(s) · {{ boutique.factures_count }} facture(s)
                                </div>
                            </div>
                            <button class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300" @click="supprimerBoutique(boutique)">
                                Supprimer
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Historique</h3>
                    <div v-if="audits.length === 0" class="text-sm text-gray-400 dark:text-gray-500">Aucune action enregistrée pour le moment.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="audit in audits" :key="audit.id" class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ actionLabels[audit.action] ?? audit.action }}
                                <span class="text-gray-400 dark:text-gray-500">par {{ audit.admin?.name ?? '—' }}</span>
                            </span>
                            <span class="text-gray-400 dark:text-gray-500">{{ formatDateHeure(audit.created_at) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">Zone dangereuse</h3>
                    <p class="text-xs text-red-700 dark:text-red-400 mt-1">
                        Supprime définitivement ce compte, ses boutiques et toutes leurs données (clients, produits, factures). Action irréversible.
                    </p>
                    <div class="mt-4">
                        <InputLabel :value="`Pour confirmer, retapez l'email : ${utilisateur.email}`" class="text-red-800 dark:text-red-300" />
                        <div class="mt-1 flex items-center gap-3">
                            <TextInput v-model="confirmationEmail" type="text" class="block w-full sm:w-80" :placeholder="utilisateur.email" />
                            <DangerButton :disabled="!peutSupprimerCompte" @click="supprimerCompte">Supprimer le compte</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
