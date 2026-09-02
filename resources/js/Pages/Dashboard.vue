<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const { t } = useI18n();

const props = defineProps({
    aucuneBoutique: {
        type: Boolean,
        default: false,
    },
    boutique: Object,
    stats: Object,
});

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + (props.boutique?.devise ?? 'XAF');

const statutClasses = {
    brouillon: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
    envoyee: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    payee: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    annulee: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};
</script>

<template>
    <AppLayout :title="t('dashboard.title')">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ t('dashboard.title') }} — {{ boutique?.nom }}
            </h2>
        </template>

        <div v-if="aucuneBoutique" class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 text-center bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-10">
                <p class="text-gray-600 dark:text-gray-300 mb-4">{{ t('dashboard.no_shop_title') }}</p>
                <Link :href="route('boutiques.create')">
                    <PrimaryButton>{{ t('dashboard.create_first_shop') }}</PrimaryButton>
                </Link>
            </div>
        </div>

        <div v-else class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('dashboard.revenue_month') }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ formatMontant(stats.finances.ca_mois) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('dashboard.expenses_month') }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ formatMontant(stats.finances.depenses_mois) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('dashboard.result_month') }}</div>
                        <div class="mt-2 text-2xl font-semibold" :class="stats.finances.resultat_mois >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ formatMontant(stats.finances.resultat_mois) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ t('dashboard.unpaid_invoices') }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ stats.factures.impayees }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ t('dashboard.clients_title') }}</div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.total') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.clients.total }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.prospects') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.clients.prospects }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.new') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.clients.nouveaux }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.regular') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.clients.reguliers }}</span></div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ t('dashboard.products_title') }}</div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.total') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.produits.total }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.active') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.produits.actifs }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.out_of_stock') }} :</span> <span class="font-semibold text-red-600 dark:text-red-400">{{ stats.produits.epuises }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.low_stock') }} :</span> <span class="font-semibold text-yellow-600 dark:text-yellow-400">{{ stats.produits.proches_rupture }}</span></div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ t('dashboard.invoices_title') }}</div>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.total') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.factures.total }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.today') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.factures.aujourdhui }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.this_month') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.factures.ce_mois }}</span></div>
                            <div><span class="text-gray-500 dark:text-gray-400">{{ t('dashboard.paid') }} :</span> <span class="font-semibold dark:text-gray-100">{{ stats.factures.payees }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                        <div class="p-6 flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('dashboard.recent_invoices') }}</h3>
                            <Link :href="route('factures.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-300">{{ t('dashboard.view_all') }}</Link>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="stats.factures_recentes.length === 0">
                                        <td class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">{{ t('dashboard.no_invoice_yet') }}</td>
                                    </tr>
                                    <tr v-for="facture in stats.factures_recentes" :key="facture.id" class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer" @click="$inertia.visit(route('factures.show', facture.id))">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ facture.numero }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ facture.client?.nom }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[facture.statut]">{{ t(`invoice_status.${facture.statut}`) }}</span>
                                        </td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-900 dark:text-gray-100">{{ formatMontant(facture.total_ttc) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ t('dashboard.best_selling_products') }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="stats.produits_plus_vendus.length === 0">
                                        <td class="px-6 py-6 text-center text-gray-400 dark:text-gray-500">{{ t('dashboard.no_sales_yet') }}</td>
                                    </tr>
                                    <tr v-for="ligne in stats.produits_plus_vendus" :key="ligne.produit_id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ ligne.produit?.nom ?? '—' }}</td>
                                        <td class="px-6 py-3 whitespace-nowrap text-sm text-right text-gray-500 dark:text-gray-400">{{ ligne.total_quantite }} {{ t('dashboard.units_sold') }}</td>
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
