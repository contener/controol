<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const avantages = [
    { titre: 'Plusieurs boutiques, un seul compte', description: 'Créez et gérez toutes vos boutiques depuis un tableau de bord unique.' },
    { titre: 'Facturation en quelques clics', description: 'Devis, factures et suivi des paiements, sans papier ni tableur.' },
    { titre: 'Boutique publique offerte', description: 'Un lien à partager sur WhatsApp et les réseaux sociaux, prêt en un instant.' },
    { titre: 'Stock toujours à jour', description: "Alertes de rupture et historique des mouvements en temps réel." },
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
</script>

<template>
    <Head title="Créer mon compte" />

    <div class="min-h-screen flex bg-slate-50 dark:bg-slate-900">
        <!-- Panneau de présentation (masqué sur mobile) -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900">
            <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-white/10 blur-3xl" />
            <div class="absolute bottom-0 right-0 w-[28rem] h-[28rem] rounded-full bg-blue-400/20 blur-3xl" />
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 24px 24px;" />

            <div class="relative z-10 flex flex-col justify-between p-12 xl:p-16 text-white w-full">
                <Link href="/" class="flex items-center gap-3">
                    <span class="flex items-center justify-center size-10 rounded-xl bg-white/15 backdrop-blur">
                        <ApplicationMark class="h-6 w-auto" />
                    </span>
                    <span class="text-lg font-semibold">Controol</span>
                </Link>

                <div class="max-w-md">
                    <h1 class="text-4xl xl:text-5xl font-bold leading-tight tracking-tight">
                        Pilotez vos boutiques
                        <span class="text-blue-200">comme un vrai pro</span>
                    </h1>
                    <p class="mt-5 text-blue-100 text-lg leading-relaxed">
                        Rejoignez la plateforme qui simplifie la gestion commerciale : produits, stock, clients, factures et boutique publique, réunis au même endroit.
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

        <!-- Formulaire -->
        <div class="flex-1 flex items-center justify-center px-4 py-12 sm:px-8">
            <div class="w-full max-w-md">
                <div class="lg:hidden flex items-center gap-3 mb-8">
                    <span class="flex items-center justify-center size-10 rounded-xl bg-blue-600">
                        <ApplicationMark class="h-6 w-auto" />
                    </span>
                    <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">Controol</span>
                </div>

                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Créez votre compte</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Gratuit, sans carte bancaire. Votre première boutique en moins de deux minutes.</p>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Nom complet</label>
                        <div class="mt-1 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 dark:text-slate-500">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                            </span>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                autofocus
                                autocomplete="name"
                                placeholder="Aïcha Ndongo"
                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 pl-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            >
                        </div>
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Adresse email</label>
                        <div class="mt-1 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 dark:text-slate-500">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="username"
                                placeholder="vous@exemple.com"
                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 pl-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            >
                        </div>
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Mot de passe</label>
                        <div class="mt-1 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 dark:text-slate-500">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 pl-10 pr-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            >
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors" @click="showPassword = !showPassword">
                                <svg v-if="showPassword" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password" />

                        <div v-if="form.password" class="mt-2">
                            <div class="flex gap-1">
                                <span
                                    v-for="i in 5"
                                    :key="i"
                                    class="h-1.5 flex-1 rounded-full transition-colors"
                                    :class="i <= forceMotDePasse.score ? forceMotDePasse.classe : 'bg-slate-200 dark:bg-slate-600'"
                                />
                            </div>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Robustesse : {{ forceMotDePasse.label }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirmer le mot de passe</label>
                        <div class="mt-1 relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 dark:text-slate-500">
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                required
                                autocomplete="new-password"
                                placeholder="••••••••"
                                class="block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 pl-10 pr-10 py-2.5 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            >
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 transition-colors" @click="showPasswordConfirmation = !showPasswordConfirmation">
                                <svg v-if="showPasswordConfirmation" class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg v-else class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature">
                        <label class="flex items-start gap-2">
                            <Checkbox v-model:checked="form.terms" name="terms" required class="mt-0.5" />
                            <span class="text-sm text-slate-600 dark:text-slate-400">
                                J'accepte les
                                <a target="_blank" :href="route('terms.show')" class="underline text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100">conditions d'utilisation</a>
                                et la
                                <a target="_blank" :href="route('policy.show')" class="underline text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100">politique de confidentialité</a>.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.terms" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:bg-blue-700 hover:shadow-md active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-60 disabled:pointer-events-none"
                    >
                        <svg v-if="form.processing" class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ form.processing ? 'Création en cours...' : 'Créer mon compte' }}
                    </button>
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
