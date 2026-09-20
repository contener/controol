<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PartageLiens from '@/Components/PartageLiens.vue';

const props = defineProps({
    lienParrainage: String,
    statistiques: Object,
    filleuls: Array,
    historique: Array,
});

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' FCFA';
const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });

const statutPaiementLabels = {
    valide: 'Validé',
    en_attente: 'En attente',
    non_paye: 'Non payé',
};

const statutPaiementClasses = {
    valide: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    en_attente: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    non_paye: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
};

const statutCommissionLabels = {
    disponible: 'Disponible',
    annulee: 'Annulée',
};
</script>

<template>
    <AppLayout title="Parrainage">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🤝 Mon parrainage</h2>
        </template>

        <div class="py-8">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Mon lien de parrainage</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3 break-all">{{ lienParrainage }}</p>
                    <PartageLiens
                        :url="lienParrainage"
                        texte="🚀 Rejoins CONTROOL pour gérer ton activité, tes produits, tes clients et tes factures. Inscris-toi avec mon lien :"
                        phrase-qr-code="Scannez ce code pour gagner de l'argent grâce à Controool"
                    />
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">👥 Comptes créés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.comptes_crees }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">💳 Paiements commencés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.paiements_commences }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">✅ Paiements validés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.paiements_valides }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">💰 Gains générés</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatMontant(statistiques.gains_generes) }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">💼 Solde disponible</div>
                        <div class="mt-1 text-lg font-semibold text-blue-600 dark:text-blue-400">{{ formatMontant(statistiques.solde_disponible) }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">💸 Gains déjà payés</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ formatMontant(statistiques.gains_payes) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="p-6 pb-0">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Mes filleuls</h3>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mt-4">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Filleul</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date d'inscription</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut paiement</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Commission</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="filleuls.length === 0">
                                <td colspan="4" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun filleul pour le moment. Partagez votre lien pour commencer.</td>
                            </tr>
                            <tr v-for="filleul in filleuls" :key="filleul.nom + filleul.date_inscription" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ filleul.nom }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(filleul.date_inscription) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutPaiementClasses[filleul.statut_paiement]">{{ statutPaiementLabels[filleul.statut_paiement] }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(filleul.commission) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <div class="p-6 pb-0">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">💰 Historique des gains</h3>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 mt-4">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Filleul</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant éligible</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Taux</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Commission</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="historique.length === 0">
                                <td colspan="6" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucune commission pour le moment.</td>
                            </tr>
                            <tr v-for="commission in historique" :key="commission.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(commission.date) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-slate-100">{{ commission.filleul ?? '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(commission.montant_eligible) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-slate-500 dark:text-slate-400">{{ commission.taux }}%</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-slate-900 dark:text-slate-100">{{ formatMontant(commission.montant_commission) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ statutCommissionLabels[commission.statut] ?? commission.statut }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
