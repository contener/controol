<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PartageLiens from '@/Components/PartageLiens.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import ContactVendeurModal from '@/Components/ContactVendeurModal.vue';

const props = defineProps({
    boutique: Object,
    produits: Array,
    categories: Array,
    filtres: Object,
    meta: Object,
    mesConversations: { type: Array, default: () => [] },
    conversationActive: { type: Object, default: null },
});

const page = usePage();
const utilisateur = computed(() => page.props.auth?.user ?? null);
const lienCreerBoutique = computed(() => (utilisateur.value ? route('boutiques.create') : route('register')));

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + props.boutique.devise;

const filtrerCategorie = (categorie) => {
    router.get(route('public.boutique', props.boutique.slug), categorie ? { categorie } : {}, { preserveState: true });
};

const lienWhatsapp = (produit) => {
    if (!props.boutique.whatsapp) {
        return null;
    }
    const numero = props.boutique.whatsapp.replace(/[^\d+]/g, '');
    const message = produit
        ? `Bonjour, je suis intéressé(e) par "${produit.nom}" sur votre boutique ${props.boutique.nom}.`
        : `Bonjour, je vous contacte depuis votre boutique ${props.boutique.nom}.`;
    return `https://wa.me/${numero.replace('+', '')}?text=${encodeURIComponent(message)}`;
};

const lienContact = computed(() => lienWhatsapp(null));

const produitActif = ref(null);

const conversationPourProduit = (produitId) => props.mesConversations.find((c) => c.produit_id === produitId) ?? null;

const ouvrirMessage = (produit) => {
    const existante = conversationPourProduit(produit.id);
    if (existante) {
        router.get(route('public.boutique', props.boutique.slug), { conversation: existante.id }, {
            only: ['conversationActive'],
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { produitActif.value = produit; },
        });
        return;
    }
    produitActif.value = produit;
};

const fermerMessage = () => {
    produitActif.value = null;
};

// Garde-fou : après un changement de produit sans rechargement (nouveau produit sans
// conversation existante), `conversationActive` peut encore contenir le fil du produit
// précédemment ouvert tant qu'aucun rechargement ne l'a remplacé — ne jamais l'afficher
// pour le mauvais produit.
const conversationPourModal = computed(() => {
    if (!produitActif.value || !props.conversationActive) {
        return null;
    }
    return props.conversationActive.produit_id === produitActif.value.id ? props.conversationActive : null;
});
</script>

<template>
    <Head :title="meta.title">
        <meta name="description" :content="meta.description">
        <meta property="og:title" :content="meta.title">
        <meta property="og:description" :content="meta.description">
        <meta property="og:url" :content="meta.url">
        <meta property="og:type" content="website">
        <meta v-if="meta.image" property="og:image" :content="meta.image">
    </Head>

    <div class="min-h-screen bg-slate-50">
        <header class="bg-white border-b border-slate-100">
            <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationMark class="h-6 w-auto" />
                    <span class="text-sm font-semibold text-slate-900 hidden sm:inline">Controol</span>
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link :href="route('marketplace.index')" class="font-medium text-slate-600 hover:text-slate-900">Marketplace</Link>
                    <Link v-if="utilisateur" :href="route('dashboard')" class="font-medium text-blue-600 hover:text-blue-500">Mon espace</Link>
                    <Link v-else :href="route('login')" class="font-medium text-blue-600 hover:text-blue-500">Se connecter</Link>
                </div>
            </div>
        </header>

        <FlashMessages />

        <div v-if="boutique.banniere_path" class="h-40 sm:h-56 w-full bg-slate-200 bg-cover bg-center" :style="`background-image: url(/storage/${boutique.banniere_path})`" />

        <div class="max-w-5xl mx-auto px-4 -mt-10 relative">
            <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <img v-if="boutique.logo_path" :src="`/storage/${boutique.logo_path}`" class="h-20 w-20 rounded-full object-cover border-4 border-white shadow" :alt="boutique.nom">
                <div v-else class="h-20 w-20 rounded-full bg-blue-100 flex items-center justify-center text-2xl font-bold text-blue-600 border-4 border-white shadow">
                    {{ boutique.nom.charAt(0) }}
                </div>

                <div class="flex-1">
                    <h1 class="text-xl font-bold text-slate-900">{{ boutique.nom }}</h1>
                    <p v-if="boutique.categorie" class="text-sm text-slate-500">{{ boutique.categorie }} · {{ boutique.ville }}</p>
                    <p v-if="boutique.description" class="mt-2 text-sm text-slate-600">{{ boutique.description }}</p>
                </div>

                <a v-if="lienContact" :href="lienContact" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 shrink-0">
                    Contacter sur WhatsApp
                </a>
            </div>

            <div class="mt-4 bg-white rounded-xl shadow-sm p-4">
                <PartageLiens :url="meta.url" :texte="`Découvrez ${boutique.nom} !`" />
            </div>

            <div v-if="categories.length > 0" class="mt-6 flex flex-wrap gap-2">
                <button
                    class="px-3 py-1 text-sm rounded-full border"
                    :class="!filtres.categorie ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300'"
                    @click="filtrerCategorie(null)"
                >
                    Tout
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat"
                    class="px-3 py-1 text-sm rounded-full border"
                    :class="filtres.categorie === cat ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-300'"
                    @click="filtrerCategorie(cat)"
                >
                    {{ cat }}
                </button>
            </div>

            <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div v-if="produits.length === 0" class="col-span-full text-center text-slate-400 py-16">
                    Aucun produit disponible pour le moment.
                </div>

                <div v-for="produit in produits" :key="produit.id" class="bg-white rounded-lg shadow-sm overflow-hidden flex flex-col">
                    <div class="aspect-square bg-slate-100">
                        <img v-if="produit.photo_path" :src="`/storage/${produit.photo_path}`" class="w-full h-full object-cover" :alt="produit.nom" loading="lazy">
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-300 text-3xl">📦</div>
                    </div>
                    <div class="p-3 flex-1 flex flex-col">
                        <h3 class="text-sm font-medium text-slate-900 line-clamp-2">{{ produit.nom }}</h3>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-900">{{ formatMontant(produit.promotion_prix ?? produit.prix_vente) }}</span>
                            <span v-if="produit.promotion_prix" class="text-xs text-slate-400 line-through">{{ formatMontant(produit.prix_vente) }}</span>
                        </div>
                        <a v-if="lienWhatsapp(produit)" :href="lienWhatsapp(produit)" target="_blank" class="mt-2 inline-flex items-center justify-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700">
                            Commander
                        </a>
                        <button type="button" class="mt-2 inline-flex items-center justify-center px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-medium rounded-md hover:bg-slate-50" @click="ouvrirMessage(produit)">
                            💬 Message
                        </button>
                    </div>
                </div>
            </div>

            <ContactVendeurModal
                :show="produitActif !== null"
                :boutique-slug="boutique.slug"
                :produit="produitActif"
                :conversation="conversationPourModal"
                @close="fermerMessage"
                @sent="fermerMessage"
            />

            <div class="mt-10 mb-16 bg-gradient-to-br from-blue-600 to-purple-700 rounded-2xl p-8 text-center text-white">
                <h2 class="text-xl font-bold">Vous souhaitez vous aussi vendre vos produits en ligne ?</h2>
                <p class="mt-2 text-blue-100 max-w-md mx-auto text-sm">
                    Créez votre propre boutique gratuitement et commencez à présenter vos produits à vos clients.
                </p>
                <Link :href="lienCreerBoutique" class="mt-5 inline-flex items-center justify-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                    Créer ma boutique gratuitement
                </Link>
            </div>
        </div>

        <footer class="text-center text-xs text-slate-400 pb-8">
            Boutique propulsée par la plateforme —
            <Link :href="route('marketplace.index')" class="underline">Découvrir la Marketplace</Link>
        </footer>
    </div>
</template>
