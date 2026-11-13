<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InstallAppBanner from '@/Components/InstallAppBanner.vue';

// L'inscription est aussi la création de la première boutique (2026-11-13) :
// formulaire en 4 étapes horizontales, mêmes champs que BoutiqueForm.vue, envoyés
// imbriqués sous "boutique.*" et validés côté serveur par les mêmes règles
// (App\Support\ReglesBoutique, voir CreateNewUser). L'utilisateur arrive ensuite
// directement sur le tableau de bord de sa boutique ; il pourra en créer d'autres
// depuis "Mes boutiques".
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
    boutique: {
        nom: '',
        categorie: '',
        description: '',
        devise: 'XAF',
        taux_tva_defaut: '19.25',
        nui: '',
        adresse: '',
        ville: '',
        pays: 'Cameroun',
        telephone: '',
        whatsapp: '',
        email: '',
        note_pied_facture: '',
        facebook_url: '',
        instagram_url: '',
        telegram_url: '',
        logo: null,
        banniere: null,
    },
});

const etapes = [
    { titre: 'Compte', sousTitre: 'Vos identifiants' },
    { titre: 'Boutique', sousTitre: 'Votre activité' },
    { titre: 'Coordonnées', sousTitre: 'Où vous trouver' },
    { titre: 'Image', sousTitre: 'Logo & réseaux' },
];

// Champs de chaque étape -- sert à revenir automatiquement sur l'étape qui
// contient une erreur renvoyée par le serveur.
const champsParEtape = [
    ['name', 'email', 'password', 'password_confirmation'],
    ['boutique.nom', 'boutique.categorie', 'boutique.description', 'boutique.devise', 'boutique.taux_tva_defaut', 'boutique.nui'],
    ['boutique.adresse', 'boutique.ville', 'boutique.pays', 'boutique.telephone', 'boutique.whatsapp', 'boutique.email'],
    ['boutique.logo', 'boutique.banniere', 'boutique.facebook_url', 'boutique.instagram_url', 'boutique.telegram_url', 'boutique.note_pied_facture', 'terms'],
];

const etape = ref(0);
const direction = ref('suivant');
const erreursLocales = ref({});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const apercuLogo = ref(null);
const apercuBanniere = ref(null);

const erreur = (champ) => erreursLocales.value[champ] ?? form.errors[champ];

const validerEtape = (index) => {
    const e = {};
    if (index === 0) {
        if (!form.name.trim()) e.name = 'Le nom est requis.';
        if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) e.email = 'Saisissez une adresse e-mail valide.';
        if (form.password.length < 8) e.password = 'Le mot de passe doit contenir au moins 8 caractères.';
        if (form.password !== form.password_confirmation) e.password_confirmation = 'Les mots de passe ne correspondent pas.';
    }
    if (index === 1 && !form.boutique.nom.trim()) {
        e['boutique.nom'] = 'Le nom de la boutique est requis.';
    }
    erreursLocales.value = e;
    return Object.keys(e).length === 0;
};

const allerA = (index) => {
    if (index === etape.value) return;
    // On peut toujours revenir en arrière ; avancer exige que les étapes
    // intermédiaires soient valides.
    if (index > etape.value) {
        for (let i = etape.value; i < index; i++) {
            if (!validerEtape(i)) {
                direction.value = i > etape.value ? 'suivant' : 'precedent';
                etape.value = i;
                return;
            }
        }
    } else {
        erreursLocales.value = {};
    }
    direction.value = index > etape.value ? 'suivant' : 'precedent';
    etape.value = index;
};

const suivant = () => allerA(etape.value + 1);
const precedent = () => allerA(etape.value - 1);

const choisirImage = (event, champ) => {
    const fichier = event.target.files[0] ?? null;
    form.boutique[champ] = fichier;
    const apercu = champ === 'logo' ? apercuLogo : apercuBanniere;
    if (apercu.value) URL.revokeObjectURL(apercu.value);
    apercu.value = fichier ? URL.createObjectURL(fichier) : null;
};

const retirerImage = (champ) => {
    form.boutique[champ] = null;
    const apercu = champ === 'logo' ? apercuLogo : apercuBanniere;
    if (apercu.value) URL.revokeObjectURL(apercu.value);
    apercu.value = null;
};

const submit = () => {
    if (etape.value < etapes.length - 1) {
        suivant();
        return;
    }
    for (let i = 0; i < etapes.length; i++) {
        if (!validerEtape(i)) {
            direction.value = 'precedent';
            etape.value = i;
            return;
        }
    }

    form.post(route('register'), {
        forceFormData: true,
        onError: (erreurs) => {
            const premiere = champsParEtape.findIndex((champs) => champs.some((c) => erreurs[c]));
            if (premiere !== -1 && premiere !== etape.value) {
                direction.value = 'precedent';
                etape.value = premiere;
            }
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const etapeAErreur = (index) => champsParEtape[index].some((c) => form.errors[c]);

const avantages = [
    { titre: 'Votre boutique prête dès l\'inscription', description: 'Nom, coordonnées, logo : tout est configuré en une seule fois.' },
    { titre: 'Facturation en quelques clics', description: 'Devis, factures et suivi des paiements, sans papier ni tableur.' },
    { titre: 'Boutique publique offerte', description: 'Un lien à partager sur WhatsApp et les réseaux sociaux, prêt en un instant.' },
    { titre: 'Plusieurs boutiques, un seul compte', description: 'Ajoutez d\'autres boutiques quand vous voulez depuis votre tableau de bord.' },
];

const forceMotDePasse = computed(() => {
    const valeur = form.password;
    if (!valeur) return { score: 0, label: '', classe: '' };

    let score = 0;
    if (valeur.length >= 8) score++;
    if (valeur.length >= 12) score++;
    if (/[A-Z]/.test(valeur) && /[a-z]/.test(valeur)) score++;
    if (/\d/.test(valeur)) score++;
    if (/[^A-Za-z0-9]/.test(valeur)) score++;

    const niveaux = [
        { label: 'Très faible', classe: 'bg-red-500' },
        { label: 'Faible', classe: 'bg-red-500' },
        { label: 'Moyen', classe: 'bg-yellow-500' },
        { label: 'Bon', classe: 'bg-blue-500' },
        { label: 'Excellent', classe: 'bg-green-500' },
    ];

    const index = Math.min(score, niveaux.length - 1);

    return { score: index + 1, ...niveaux[index] };
});

const champClasse = 'block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm';
const libelleClasse = 'block text-sm font-medium text-slate-700 dark:text-slate-300';
</script>

<template>
    <Head title="Créer mon compte" />

    <InstallAppBanner />

    <div class="min-h-screen flex bg-slate-50 dark:bg-slate-900">
        <!-- Panneau de présentation (masqué sur mobile) -->
        <div class="hidden lg:flex lg:w-5/12 relative overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white/10 blur-3xl" />
            <div class="absolute bottom-0 right-0 w-[28rem] h-[28rem] rounded-full bg-blue-400/20 blur-3xl" />
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 24px 24px;" />

            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 text-white w-full">
                <Link href="/" class="flex items-center gap-3">
                    <span class="flex items-center justify-center size-10 rounded-xl bg-white shadow-sm">
                        <ApplicationMark class="h-6 w-auto" />
                    </span>
                    <span class="text-lg font-semibold">Controol</span>
                </Link>

                <div class="max-w-md">
                    <h1 class="text-4xl xl:text-5xl font-bold leading-tight tracking-tight">
                        Créez votre compte
                        <span class="text-blue-200">et votre boutique</span>
                    </h1>
                    <p class="mt-5 text-blue-100 text-lg leading-relaxed">
                        En quelques étapes, votre boutique est prête : produits, stock, clients, factures et boutique publique, réunis au même endroit.
                    </p>

                    <ul class="mt-10 space-y-5">
                        <li v-for="avantage in avantages" :key="avantage.titre" class="flex items-start gap-3">
                            <span class="mt-0.5 flex items-center justify-center size-6 rounded-full bg-white/15 shrink-0">
                                <svg class="size-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </span>
                            <div>
                                <div class="font-medium">{{ avantage.titre }}</div>
                                <div class="text-sm text-blue-200">{{ avantage.description }}</div>
                            </div>
                        </li>
                    </ul>
                </div>

                <p class="text-sm text-blue-200">
                    Déjà inscrit·e ?
                    <Link :href="route('login')" class="font-medium text-white underline underline-offset-2 hover:text-blue-100">
                        Connectez-vous
                    </Link>
                </p>
            </div>
        </div>

        <!-- Formulaire en étapes -->
        <div class="flex-1 flex items-start sm:items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-xl">
                <div class="lg:hidden flex items-center gap-3 mb-6">
                    <span class="flex items-center justify-center size-10 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm">
                        <ApplicationMark class="h-6 w-auto" />
                    </span>
                    <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">Controol</span>
                </div>

                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Créez votre compte et votre boutique</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Gratuit, sans carte bancaire. Votre première boutique est créée en même temps que votre compte.</p>

                <div v-if="$page.props.flash?.error" class="mt-6 rounded-md bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-300">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Indicateur d'étapes horizontal -->
                <ol class="mt-8 flex items-start">
                    <li v-for="(e, index) in etapes" :key="e.titre" class="flex-1 flex items-start" :class="{ 'flex-none': index === etapes.length - 1 }">
                        <button type="button" class="group flex flex-col items-center text-center focus:outline-none" @click="allerA(index)">
                            <span
                                class="flex items-center justify-center size-9 rounded-full text-sm font-semibold ring-4 transition-all duration-200"
                                :class="[
                                    etapeAErreur(index)
                                        ? 'bg-red-500 text-white ring-red-100 dark:ring-red-900/40'
                                        : index < etape
                                            ? 'bg-blue-600 text-white ring-blue-100 dark:ring-blue-900/40'
                                            : index === etape
                                                ? 'bg-blue-600 text-white ring-blue-200 dark:ring-blue-800 scale-110'
                                                : 'bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-500 ring-slate-100 dark:ring-slate-800 border border-slate-300 dark:border-slate-600',
                                ]"
                            >
                                <svg v-if="index < etape && !etapeAErreur(index)" class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                <span v-else>{{ index + 1 }}</span>
                            </span>
                            <span class="mt-2 text-xs font-semibold" :class="index <= etape ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500'">{{ e.titre }}</span>
                            <span class="hidden sm:block text-[11px] text-slate-400 dark:text-slate-500">{{ e.sousTitre }}</span>
                        </button>
                        <div v-if="index < etapes.length - 1" class="flex-1 h-0.5 mt-[18px] mx-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full bg-blue-600 transition-all duration-300" :style="{ width: index < etape ? '100%' : '0%' }" />
                        </div>
                    </li>
                </ol>

                <form class="mt-8" @submit.prevent="submit">
                    <div class="relative overflow-hidden">
                        <Transition
                            mode="out-in"
                            :enter-from-class="direction === 'suivant' ? 'opacity-0 translate-x-8' : 'opacity-0 -translate-x-8'"
                            enter-active-class="transition duration-200 ease-out"
                            :leave-to-class="direction === 'suivant' ? 'opacity-0 -translate-x-8' : 'opacity-0 translate-x-8'"
                            leave-active-class="transition duration-150 ease-in"
                        >
                            <!-- Étape 1 : compte -->
                            <div v-if="etape === 0" key="compte" class="space-y-5">
                                <template v-if="$page.props.google.active">
                                    <a
                                        :href="route('auth.google.redirect')"
                                        class="w-full flex items-center justify-center gap-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-100 shadow-sm transition-all duration-150 hover:bg-slate-50 dark:hover:bg-slate-600 hover:shadow-md active:scale-[0.99]"
                                    >
                                        <svg class="size-5" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                            <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z" />
                                            <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z" />
                                            <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z" />
                                            <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z" />
                                        </svg>
                                        S'inscrire avec Google
                                    </a>
                                    <div class="relative">
                                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200 dark:border-slate-700" /></div>
                                        <div class="relative flex justify-center text-xs">
                                            <span class="bg-slate-50 dark:bg-slate-900 px-2 text-slate-400 dark:text-slate-500">ou avec votre email</span>
                                        </div>
                                    </div>
                                </template>

                                <div>
                                    <label for="name" :class="libelleClasse">Nom complet *</label>
                                    <input id="name" v-model="form.name" type="text" autofocus autocomplete="name" placeholder="Aïcha Ndongo" :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('name')" />
                                </div>

                                <div>
                                    <label for="email" :class="libelleClasse">Adresse email *</label>
                                    <input id="email" v-model="form.email" type="email" autocomplete="username" placeholder="vous@exemple.com" :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('email')" />
                                </div>

                                <div>
                                    <label for="password" :class="libelleClasse">Mot de passe *</label>
                                    <div class="mt-1 relative">
                                        <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" placeholder="••••••••" :class="[champClasse, 'pr-10']">
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" @click="showPassword = !showPassword">
                                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path v-if="showPassword" stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="erreur('password')" />
                                    <div v-if="form.password" class="mt-2">
                                        <div class="flex gap-1">
                                            <span v-for="i in 5" :key="i" class="h-1.5 flex-1 rounded-full transition-colors" :class="i <= forceMotDePasse.score ? forceMotDePasse.classe : 'bg-slate-200 dark:bg-slate-600'" />
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Robustesse : {{ forceMotDePasse.label }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label for="password_confirmation" :class="libelleClasse">Confirmer le mot de passe *</label>
                                    <div class="mt-1 relative">
                                        <input id="password_confirmation" v-model="form.password_confirmation" :type="showPasswordConfirmation ? 'text' : 'password'" autocomplete="new-password" placeholder="••••••••" :class="[champClasse, 'pr-10']">
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="erreur('password_confirmation')" />
                                </div>
                            </div>

                            <!-- Étape 2 : la boutique -->
                            <div v-else-if="etape === 1" key="boutique" class="space-y-5">
                                <div>
                                    <label for="b_nom" :class="libelleClasse">Nom de la boutique *</label>
                                    <input id="b_nom" v-model="form.boutique.nom" type="text" autocomplete="organization" placeholder="Ex. : Aïcha Mode" :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('boutique.nom')" />
                                </div>
                                <div>
                                    <label for="b_categorie" :class="libelleClasse">Catégorie</label>
                                    <input id="b_categorie" v-model="form.boutique.categorie" type="text" placeholder="Informatique, Vêtements..." :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('boutique.categorie')" />
                                </div>
                                <div>
                                    <label for="b_description" :class="libelleClasse">Description</label>
                                    <textarea id="b_description" v-model="form.boutique.description" rows="3" placeholder="Ce que vous vendez, ce qui vous distingue..." :class="[champClasse, 'mt-1']" />
                                    <InputError class="mt-2" :message="erreur('boutique.description')" />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="b_devise" :class="libelleClasse">Devise</label>
                                        <input id="b_devise" v-model="form.boutique.devise" type="text" maxlength="3" :class="[champClasse, 'mt-1 uppercase']">
                                        <InputError class="mt-2" :message="erreur('boutique.devise')" />
                                    </div>
                                    <div>
                                        <label for="b_tva" :class="libelleClasse">TVA par défaut (%)</label>
                                        <input id="b_tva" v-model="form.boutique.taux_tva_defaut" type="number" step="0.01" min="0" max="100" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.taux_tva_defaut')" />
                                    </div>
                                </div>
                                <div>
                                    <label for="b_nui" :class="libelleClasse">NUI (Numéro d'Identifiant Unique)</label>
                                    <input id="b_nui" v-model="form.boutique.nui" type="text" placeholder="M012312345678A" :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('boutique.nui')" />
                                </div>
                            </div>

                            <!-- Étape 3 : coordonnées -->
                            <div v-else-if="etape === 2" key="coordonnees" class="space-y-5">
                                <div>
                                    <label for="b_adresse" :class="libelleClasse">Adresse</label>
                                    <input id="b_adresse" v-model="form.boutique.adresse" type="text" autocomplete="street-address" placeholder="Quartier, rue..." :class="[champClasse, 'mt-1']">
                                    <InputError class="mt-2" :message="erreur('boutique.adresse')" />
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="b_ville" :class="libelleClasse">Ville</label>
                                        <input id="b_ville" v-model="form.boutique.ville" type="text" placeholder="Douala" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.ville')" />
                                    </div>
                                    <div>
                                        <label for="b_pays" :class="libelleClasse">Pays</label>
                                        <input id="b_pays" v-model="form.boutique.pays" type="text" autocomplete="country-name" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.pays')" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="b_telephone" :class="libelleClasse">Téléphone</label>
                                        <input id="b_telephone" v-model="form.boutique.telephone" type="tel" autocomplete="tel" placeholder="+237600000000" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.telephone')" />
                                    </div>
                                    <div>
                                        <label for="b_whatsapp" :class="libelleClasse">Numéro WhatsApp</label>
                                        <input id="b_whatsapp" v-model="form.boutique.whatsapp" type="tel" placeholder="+237600000000" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.whatsapp')" />
                                    </div>
                                </div>
                                <div>
                                    <label for="b_email" :class="libelleClasse">Email de la boutique</label>
                                    <input id="b_email" v-model="form.boutique.email" type="email" :placeholder="form.email || 'contact@maboutique.com'" :class="[champClasse, 'mt-1']">
                                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Laissé vide, l'email de votre compte sera utilisé.</p>
                                    <InputError class="mt-2" :message="erreur('boutique.email')" />
                                </div>
                            </div>

                            <!-- Étape 4 : apparence, réseaux et facture -->
                            <div v-else key="image" class="space-y-5">
                                <div>
                                    <span :class="libelleClasse">Bannière</span>
                                    <label class="mt-1 relative flex items-center justify-center h-32 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden cursor-pointer hover:border-blue-400 transition-colors">
                                        <img v-if="apercuBanniere" :src="apercuBanniere" class="absolute inset-0 w-full h-full object-cover" alt="Aperçu de la bannière">
                                        <span v-else class="text-sm text-slate-400 dark:text-slate-500">Cliquez pour ajouter une bannière</span>
                                        <input type="file" accept="image/*" class="sr-only" @change="choisirImage($event, 'banniere')">
                                    </label>
                                    <button v-if="apercuBanniere" type="button" class="mt-1 text-xs text-red-600 hover:underline" @click="retirerImage('banniere')">Retirer</button>
                                    <InputError class="mt-2" :message="erreur('boutique.banniere')" />
                                </div>

                                <div class="flex items-center gap-4">
                                    <label class="relative flex items-center justify-center size-20 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 overflow-hidden cursor-pointer hover:border-blue-400 transition-colors shrink-0">
                                        <img v-if="apercuLogo" :src="apercuLogo" class="absolute inset-0 w-full h-full object-cover" alt="Aperçu du logo">
                                        <span v-else class="text-xs text-slate-400 dark:text-slate-500 text-center px-2">Logo</span>
                                        <input type="file" accept="image/*" class="sr-only" @change="choisirImage($event, 'logo')">
                                    </label>
                                    <div class="text-sm">
                                        <p class="font-medium text-slate-700 dark:text-slate-300">Logo de la boutique</p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500">Affiché sur votre boutique publique et vos factures.</p>
                                        <button v-if="apercuLogo" type="button" class="mt-1 text-xs text-red-600 hover:underline" @click="retirerImage('logo')">Retirer</button>
                                    </div>
                                </div>
                                <InputError :message="erreur('boutique.logo')" />

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label for="b_fb" :class="libelleClasse">Lien Facebook</label>
                                        <input id="b_fb" v-model="form.boutique.facebook_url" type="url" placeholder="https://" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.facebook_url')" />
                                    </div>
                                    <div>
                                        <label for="b_ig" :class="libelleClasse">Lien Instagram</label>
                                        <input id="b_ig" v-model="form.boutique.instagram_url" type="url" placeholder="https://" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.instagram_url')" />
                                    </div>
                                    <div>
                                        <label for="b_tg" :class="libelleClasse">Lien Telegram</label>
                                        <input id="b_tg" v-model="form.boutique.telegram_url" type="url" placeholder="https://" :class="[champClasse, 'mt-1']">
                                        <InputError class="mt-2" :message="erreur('boutique.telegram_url')" />
                                    </div>
                                </div>

                                <div>
                                    <label for="b_note" :class="libelleClasse">Note de bas de facture</label>
                                    <textarea id="b_note" v-model="form.boutique.note_pied_facture" rows="2" placeholder="Ex. : La garantie ne couvre pas les dommages liés à une mauvaise utilisation." :class="[champClasse, 'mt-1']" />
                                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Affichée automatiquement en bas de toutes vos factures.</p>
                                    <InputError class="mt-2" :message="erreur('boutique.note_pied_facture')" />
                                </div>

                                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature">
                                    <label class="flex items-start gap-2">
                                        <Checkbox v-model:checked="form.terms" name="terms" required class="mt-0.5" />
                                        <span class="text-sm text-slate-600 dark:text-slate-400">
                                            J'accepte les
                                            <a target="_blank" :href="route('terms.show')" class="underline text-slate-700 dark:text-slate-300">conditions d'utilisation</a>
                                            et la
                                            <a target="_blank" :href="route('policy.show')" class="underline text-slate-700 dark:text-slate-300">politique de confidentialité</a>.
                                        </span>
                                    </label>
                                    <InputError class="mt-2" :message="form.errors.terms" />
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Navigation entre les étapes -->
                    <div class="mt-8 flex items-center gap-3">
                        <button
                            v-if="etape > 0"
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
                            @click="precedent"
                        >
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                            Retour
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex-1 flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:bg-blue-700 hover:shadow-md active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-60 disabled:pointer-events-none"
                        >
                            <svg v-if="form.processing" class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            <template v-if="etape < etapes.length - 1">
                                Continuer
                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                            </template>
                            <template v-else>{{ form.processing ? 'Création en cours...' : 'Créer mon compte et ma boutique' }}</template>
                        </button>
                    </div>
                    <p v-if="etape >= 2" class="mt-3 text-center text-xs text-slate-400 dark:text-slate-500">
                        Ces informations sont facultatives : vous pourrez les compléter plus tard depuis « Mes boutiques ».
                    </p>
                </form>

                <p class="lg:hidden mt-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    Déjà inscrit·e ?
                    <Link :href="route('login')" class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300">Connectez-vous</Link>
                </p>

                <p class="mt-8 flex items-center justify-center gap-1.5 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m6-3v8.25a9 9 0 01-9 9 9 9 0 01-9-9V6.75L12 3l9 3.75z" />
                    </svg>
                    Vos données sont chiffrées et ne sont jamais partagées.
                </p>
            </div>
        </div>
    </div>
</template>
