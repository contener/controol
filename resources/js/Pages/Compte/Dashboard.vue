<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    stats: Object,
    filtres: Object,
});

const debut = ref(props.filtres.debut ?? props.stats.periode.debut);
const fin = ref(props.filtres.fin ?? props.stats.periode.fin);

watch([debut, fin], () => {
    router.get(route('compte.dashboard'), { debut: debut.value, fin: fin.value }, {
        preserveState: true,
        replace: true,
    });
});

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' XAF';

const statutLabels = {
    brouillon: 'Brouillon',
    envoyee: 'Envoyée',
    payee: 'Payée',
    annulee: 'Annulée',
};

const statutClasses = {
    brouillon: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
    envoyee: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    payee: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    annulee: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};
</script>

<template>
    <AppLayout title="Vue globale">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Vue globale de mon compte</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Boutiques</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ stats.boutiques.total }}</div>
                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ stats.boutiques.actives }} active(s) · {{ stats.boutiques.suspendues }} suspendue(s)</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Clients (toutes boutiques)</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ stats.clients.total }}</div>
                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ stats.clients.prospects }} prospects · {{ stats.clients.reguliers }} réguliers</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Produits en rupture</div>
                        <div class="mt-2 text-2xl font-semibold" :class="stats.produits_en_rupture > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'">{{ stats.produits_en_rupture }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Meilleure boutique (mois)</div>
                        <div class="mt-2 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ stats.meilleure_boutique?.boutique?.nom ?? '—' }}</div>
                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-1" v-if="stats.meilleure_boutique">{{ formatMontant(stats.meilleure_boutique.ca_mois) }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
                        <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Chiffre d'affaires &amp; dépenses</h3>
                        <div class="flex items-end gap-3">
                            <div>
                                <InputLabel value="Du" />
                                <TextInput v-model="debut" type="date" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Au" />
                                <TextInput v-model="fin" type="date" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Aujourd'hui</span><span class="font-medium">{{ formatMontant(stats.chiffre_affaires.aujourdhui) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Cette semaine</span><span class="font-medium">{{ formatMontant(stats.chiffre_affaires.cette_semaine) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Ce mois</span><span class="font-medium">{{ formatMontant(stats.chiffre_affaires.ce_mois) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Cette année</span><span class="font-medium">{{ formatMontant(stats.chiffre_affaires.cette_annee) }}</span></div>
                            <div class="flex justify-between font-semibold border-t border-slate-200 dark:border-slate-700 pt-1 mt-1"><span>Période sélectionnée</span><span>{{ formatMontant(stats.chiffre_affaires.periode) }}</span></div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Dépenses aujourd'hui</span><span class="font-medium">{{ formatMontant(stats.depenses.aujourdhui) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Dépenses cette semaine</span><span class="font-medium">{{ formatMontant(stats.depenses.cette_semaine) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Dépenses ce mois</span><span class="font-medium">{{ formatMontant(stats.depenses.ce_mois) }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Dépenses cette année</span><span class="font-medium">{{ formatMontant(stats.depenses.cette_annee) }}</span></div>
                            <div class="flex justify-between font-semibold border-t border-slate-200 dark:border-slate-700 pt-1 mt-1">
                                <span>Résultat (période)</span>
                                <span :class="stats.resultat_periode >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ formatMontant(stats.resultat_periode) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Classement des boutiques (CA du mois)</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr v-for="entree in stats.classement_boutiques" :key="entree.boutique.id" class="hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer" @click="$inertia.visit(route('boutiques.index'))">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ entree.boutique.nom }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(entree.ca_mois) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg">
                        <div class="p-6 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100">Factures récentes</h3>
                            <Link :href="route('factures.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Voir tout</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                                    <tr v-if="stats.factures_recentes.length === 0">
                                        <td class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucune facture pour le moment.</td>
                                    </tr>
                                    <tr v-for="facture in stats.factures_recentes" :key="facture.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ facture.numero }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ facture.boutique?.nom }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[facture.statut]">{{ statutLabels[facture.statut] }}</span>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-slate-900 dark:text-slate-100">{{ formatMontant(facture.total_ttc) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
