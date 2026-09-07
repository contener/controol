<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import PartageLiens from '@/Components/PartageLiens.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    boutique: Object,
    produits: Array,
    categories: Array,
    filtres: Object,
    meta: Object,
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

const produitMessage = ref(null);
const formMessage = useForm({
    nom_visiteur: '',
    contact_visiteur: '',
    contenu: '',
    produit_id: null,
});

const ouvrirMessage = (produit) => {
    produitMessage.value = produit;
    formMessage.reset();
    formMessage.clearErrors();
    formMessage.produit_id = produit.id;
    formMessage.contenu = `Bonjour, je suis intéressé(e) par "${produit.nom}".`;
};

const fermerMessage = () => {
    produitMessage.value = null;
};

const envoyerMessage = () => {
    formMessage.post(route('public.boutique.message', props.boutique.slug), {
        preserveScroll: true,
        onSuccess: () => fermerMessage(),
    });
};
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

    <div class="min-h-screen bg-gray-50">
        <header class="bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationMark class="h-6 w-auto" />
                    <span class="text-sm font-semibold text-gray-900 hidden sm:inline">Controol</span>
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link :href="route('marketplace.index')" class="font-medium text-gray-600 hover:text-gray-900">Marketplace</Link>
                    <Link v-if="utilisateur" :href="route('dashboard')" class="font-medium text-indigo-600 hover:text-indigo-500">Mon espace</Link>
                    <Link v-else :href="route('login')" class="font-medium text-indigo-600 hover:text-indigo-500">Se connecter</Link>
                </div>
            </div>
        </header>

        <FlashMessages />

        <div v-if="boutique.banniere_path" class="h-40 sm:h-56 w-full bg-gray-200 bg-cover bg-center" :style="`background-image: url(/storage/${boutique.banniere_path})`" />

        <div class="max-w-5xl mx-auto px-4 -mt-10 relative">
            <div class="bg-white rounded-xl shadow-sm p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <img v-if="boutique.logo_path" :src="`/storage/${boutique.logo_path}`" class="h-20 w-20 rounded-full object-cover border-4 border-white shadow" :alt="boutique.nom">
                <div v-else class="h-20 w-20 rounded-full bg-indigo-100 flex items-center justify-center text-2xl font-bold text-indigo-600 border-4 border-white shadow">
                    {{ boutique.nom.charAt(0) }}
                </div>

                <div class="flex-1">
                    <h1 class="text-xl font-bold text-gray-900">{{ boutique.nom }}</h1>
                    <p v-if="boutique.categorie" class="text-sm text-gray-500">{{ boutique.categorie }} · {{ boutique.ville }}</p>
                    <p v-if="boutique.description" class="mt-2 text-sm text-gray-600">{{ boutique.description }}</p>
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
                    :class="!filtres.categorie ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300'"
                    @click="filtrerCategorie(null)"
                >
                    Tout
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat"
                    class="px-3 py-1 text-sm rounded-full border"
                    :class="filtres.categorie === cat ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300'"
                    @click="filtrerCategorie(cat)"
                >
                    {{ cat }}
                </button>
            </div>

            <div class="mt-6 grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div v-if="produits.length === 0" class="col-span-full text-center text-gray-400 py-16">
                    Aucun produit disponible pour le moment.
                </div>

                <div v-for="produit in produits" :key="produit.id" class="bg-white rounded-lg shadow-sm overflow-hidden flex flex-col">
                    <div class="aspect-square bg-gray-100">
                        <img v-if="produit.photo_path" :src="`/storage/${produit.photo_path}`" class="w-full h-full object-cover" :alt="produit.nom" loading="lazy">
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-3xl">📦</div>
                    </div>
                    <div class="p-3 flex-1 flex flex-col">
                        <h3 class="text-sm font-medium text-gray-900 line-clamp-2">{{ produit.nom }}</h3>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900">{{ formatMontant(produit.promotion_prix ?? produit.prix_vente) }}</span>
                            <span v-if="produit.promotion_prix" class="text-xs text-gray-400 line-through">{{ formatMontant(produit.prix_vente) }}</span>
                        </div>
                        <a v-if="lienWhatsapp(produit)" :href="lienWhatsapp(produit)" target="_blank" class="mt-2 inline-flex items-center justify-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700">
                            Commander
                        </a>
                        <button type="button" class="mt-2 inline-flex items-center justify-center px-3 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-md hover:bg-gray-50" @click="ouvrirMessage(produit)">
                            💬 Message
                        </button>
                    </div>
                </div>
            </div>

            <DialogModal :show="produitMessage !== null" @close="fermerMessage">
                <template #title>Laisser un message au vendeur</template>
                <template #content>
                    <p class="text-sm text-gray-500 mb-4" v-if="produitMessage">À propos de « {{ produitMessage.nom }} »</p>

                    <div>
                        <InputLabel for="nom_visiteur" value="Votre nom" />
                        <TextInput id="nom_visiteur" v-model="formMessage.nom_visiteur" class="mt-1 block w-full" autocomplete="name" />
                        <InputError :message="formMessage.errors.nom_visiteur" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="contact_visiteur" value="Téléphone ou email (optionnel)" />
                        <TextInput id="contact_visiteur" v-model="formMessage.contact_visiteur" class="mt-1 block w-full" placeholder="Pour que le vendeur puisse vous répondre" />
                        <InputError :message="formMessage.errors.contact_visiteur" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="contenu" value="Votre message" />
                        <textarea id="contenu" v-model="formMessage.contenu" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                        <InputError :message="formMessage.errors.contenu" class="mt-2" />
                    </div>
                </template>
                <template #footer>
                    <SecondaryButton @click="fermerMessage">Annuler</SecondaryButton>
                    <PrimaryButton class="ms-3" :class="{ 'opacity-25': formMessage.processing }" :disabled="formMessage.processing" @click="envoyerMessage">
                        Envoyer
                    </PrimaryButton>
                </template>
            </DialogModal>

            <div class="mt-10 mb-16 bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-8 text-center text-white">
                <h2 class="text-xl font-bold">Vous souhaitez vous aussi vendre vos produits en ligne ?</h2>
                <p class="mt-2 text-indigo-100 max-w-md mx-auto text-sm">
                    Créez votre propre boutique gratuitement et commencez à présenter vos produits à vos clients.
                </p>
                <Link :href="lienCreerBoutique" class="mt-5 inline-flex items-center justify-center rounded-lg bg-white px-6 py-3 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    Créer ma boutique gratuitement
                </Link>
            </div>
        </div>

        <footer class="text-center text-xs text-gray-400 pb-8">
            Boutique propulsée par la plateforme —
            <Link :href="route('marketplace.index')" class="underline">Découvrir la Marketplace</Link>
        </footer>
    </div>
</template>
