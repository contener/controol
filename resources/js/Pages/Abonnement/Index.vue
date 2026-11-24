<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    planActif: Object,
    usage: Object,
    plans: Array,
    paiementEnAttente: Object,
    essaiActif: {
        type: Object,
        default: null,
    },
    // Historique des paiements (App\Support\HistoriquePaiements) : champs en liste
    // blanche, références masquées.
    historiquePaiements: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
});

const formatMontant = (montant, devise = 'XAF') => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + devise;
const formatDate = (iso) => (iso ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '');
const formatJour = (iso) => (iso ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(iso)) : '');

const classesStatut = {
    paye: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    refuse: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    echoue: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    en_cours: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    en_attente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
};

// Pendant un essai, planActif.code vaut "basique" mais aucun paiement n'a encore ete
// effectue -- le plan Basique ne doit donc jamais etre traite comme "deja actif"
// (bouton desactive) : c'est justement le plan que l'essai invite a payer.
const dejaSurCePlan = (plan) => plan.code === props.planActif?.code && !(props.essaiActif && plan.code === 'basique');

const choisirPlan = (plan) => {
    if (dejaSurCePlan(plan)) {
        return;
    }
    const prix = (props.essaiActif && plan.code === 'basique') ? props.essaiActif.prix_promo : plan.prix;
    const message = plan.lien_paiement
        ? `Passer au plan ${plan.nom} (${formatMontant(prix, plan.devise)}) ? Vous allez être redirigé vers la page de paiement. Votre abonnement sera activé dès vérification du paiement.`
        : `Passer au plan ${plan.nom} (${formatMontant(prix, plan.devise)}) ?`;

    if (confirm(message)) {
        router.post(route('abonnement.changer', plan.id));
    }
};

const limiteLabel = (limite) => (limite === null ? 'Illimité' : limite);
</script>

<template>
    <AppLayout title="Abonnement">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Mon abonnement</h2>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="paiementEnAttente" class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 px-4 py-3 text-sm text-yellow-800 dark:text-yellow-300">
                    Un paiement de {{ formatMontant(paiementEnAttente.montant, paiementEnAttente.devise) }} est en attente de confirmation pour activer votre nouvel abonnement.
                </div>

                <div v-if="essaiActif" class="rounded-lg bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 text-white">
                    <p class="font-semibold">
                        🎁 Essai Basique en cours — encore {{ essaiActif.jours_restants }} jour{{ essaiActif.jours_restants > 1 ? 's' : '' }} (fin le {{ essaiActif.date_fin }})
                    </p>
                    <p class="text-sm text-blue-100 mt-0.5">
                        Abonnez-vous avant la fin de votre essai et payez {{ formatMontant(essaiActif.prix_promo) }} au lieu de {{ formatMontant(essaiActif.prix_normal) }} pour le plan Basique.
                    </p>
                </div>

                <div v-if="planActif?.marketplace" class="rounded-lg bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4 text-white flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">🎉 Votre abonnement est actif !</p>
                        <p class="text-sm text-blue-100 mt-0.5">Vous pouvez maintenant publier vos boutiques sur la Marketplace.</p>
                    </div>
                    <Link :href="route('boutiques.index')" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                        Voir mes boutiques
                    </Link>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-slate-900 dark:text-slate-100 mb-4">Plan actuel : {{ planActif?.nom ?? 'Aucun' }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Boutiques</div>
                            <div class="font-semibold">{{ usage.boutiques.utilise }} / {{ limiteLabel(usage.boutiques.limite) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Produits/Services</div>
                            <div class="font-semibold">{{ usage.produits.utilise }} / {{ limiteLabel(usage.produits.limite) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Clients</div>
                            <div class="font-semibold">{{ usage.clients.utilise }} / {{ limiteLabel(usage.clients.limite) }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Factures</div>
                            <div class="font-semibold">{{ usage.factures.utilise }} / {{ limiteLabel(usage.factures.limite) }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div v-for="plan in plans" :key="plan.id" class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6 flex flex-col" :class="plan.code === planActif?.code ? 'ring-2 ring-blue-500' : ''">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ plan.nom }}</h3>
                        <template v-if="essaiActif && plan.code === 'basique'">
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ formatMontant(essaiActif.prix_promo, plan.devise) }}</span>
                                <span class="text-sm text-slate-400 line-through">{{ formatMontant(plan.prix, plan.devise) }}</span>
                            </div>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">Offre essai — encore {{ essaiActif.jours_restants }} jour{{ essaiActif.jours_restants > 1 ? 's' : '' }}</p>
                        </template>
                        <div v-else class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatMontant(plan.prix, plan.devise) }}</div>

                        <ul class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300 flex-1">
                            <li>Boutiques : {{ limiteLabel(plan.limite_boutiques) }}</li>
                            <li>Produits : {{ limiteLabel(plan.limite_produits) }}</li>
                            <li>Clients : {{ limiteLabel(plan.limite_clients) }}</li>
                            <li>Factures : {{ limiteLabel(plan.limite_factures) }}</li>
                            <li>Stocks suivis : {{ limiteLabel(plan.limite_stocks) }}</li>
                            <li>Marketplace : {{ plan.marketplace ? 'Oui' : 'Non' }}</li>
                            <li>Publication sociale : {{ plan.publication_sociale ? 'Oui' : 'Non' }}</li>
                            <li>Chatbot WhatsApp : {{ plan.chatbot_whatsapp ? 'Prévu' : 'Non' }}</li>
                        </ul>

                        <PrimaryButton class="mt-6 justify-center" :disabled="dejaSurCePlan(plan)" @click="choisirPlan(plan)">
                            {{ dejaSurCePlan(plan) ? 'Plan actif' : (essaiActif && plan.code === 'basique' ? "S'abonner (offre essai)" : 'Choisir ce plan') }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Historique des paiements -->
                <div id="historique" class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Historique des paiements</h3>
                    </div>
                    <div v-if="historiquePaiements.data.length === 0" class="px-6 py-8 text-center text-sm text-slate-400 dark:text-slate-500">
                        Aucun paiement pour le moment.
                    </div>
                    <ul v-else class="divide-y divide-slate-200 dark:divide-slate-700">
                        <li v-for="(p, index) in historiquePaiements.data" :key="index" class="px-6 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ p.plan ?? 'Abonnement' }} — {{ formatMontant(p.montant, p.devise) }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ formatDate(p.cree_le) }} · {{ p.moyen }}<span v-if="p.reference"> · Réf. {{ p.reference }}</span>
                                    </div>
                                </div>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="classesStatut[p.statut]">{{ p.statut_libelle }}</span>
                            </div>
                            <p v-if="p.statut === 'paye' && p.abonnement_jusqu_au" class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">Abonnement actif jusqu'au {{ formatJour(p.abonnement_jusqu_au) }}</p>
                            <p v-if="p.motif_rejet" class="mt-1 text-xs text-red-700 dark:text-red-400">Motif : {{ p.motif_rejet }}</p>
                            <p v-if="p.statut === 'echoue'" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Aucun montant n'a été validé. Vous pouvez réessayer.</p>
                            <details v-if="p.tentatives.length > 1" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <summary class="cursor-pointer select-none">{{ p.tentatives.length }} tentatives</summary>
                                <ul class="mt-1 space-y-0.5 pl-4 list-disc">
                                    <li v-for="(t, i) in p.tentatives" :key="i">{{ formatDate(t.le) }} — {{ t.statut_libelle }} (Réf. {{ t.reference }})</li>
                                </ul>
                            </details>
                        </li>
                    </ul>
                    <div v-if="historiquePaiements.links && historiquePaiements.links.length > 3" class="px-6 py-3 flex flex-wrap gap-1 border-t border-slate-200 dark:border-slate-700">
                        <Link
                            v-for="(link, index) in historiquePaiements.links"
                            :key="index"
                            :href="link.url ? link.url + '#historique' : '#'"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'"
                            preserve-scroll
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
