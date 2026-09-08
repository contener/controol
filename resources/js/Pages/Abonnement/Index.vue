<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    planActif: Object,
    usage: Object,
    plans: Array,
    paiementEnAttente: Object,
});

const formatMontant = (montant, devise = 'XAF') => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + devise;

const choisirPlan = (plan) => {
    if (plan.code === props.planActif?.code) {
        return;
    }
    const message = plan.lien_paiement
        ? `Passer au plan ${plan.nom} (${formatMontant(plan.prix, plan.devise)}) ? Vous allez être redirigé vers la page de paiement. Votre abonnement sera activé dès vérification du paiement.`
        : `Passer au plan ${plan.nom} (${formatMontant(plan.prix, plan.devise)}) ?`;

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
                        <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{{ formatMontant(plan.prix, plan.devise) }}</div>

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

                        <PrimaryButton class="mt-6 justify-center" :disabled="plan.code === planActif?.code" @click="choisirPlan(plan)">
                            {{ plan.code === planActif?.code ? 'Plan actif' : 'Choisir ce plan' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
