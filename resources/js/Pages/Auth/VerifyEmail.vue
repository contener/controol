<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: String,
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Vérification de l'e-mail" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <h1 class="text-center text-lg font-semibold text-slate-900 dark:text-slate-100 mb-1">Vérifiez votre e-mail</h1>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400 mb-4">
            Avant de continuer, merci de confirmer votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer.
            Si vous ne l'avez pas reçu, nous pouvons vous en renvoyer un.
        </p>

        <div v-if="verificationLinkSent" class="mb-4 font-medium text-sm text-green-600 dark:text-green-400 text-center">
            Un nouveau lien de vérification a été envoyé à l'adresse e-mail renseignée dans votre profil.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4">
                <PrimaryButton class="w-full sm:w-auto justify-center" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Envoi...' : 'Renvoyer l\'e-mail de vérification' }}
                </PrimaryButton>

                <div class="flex items-center gap-4">
                    <Link
                        :href="route('profile.show')"
                        class="underline text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-slate-800 focus:ring-blue-500"
                    >
                        Modifier le profil
                    </Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="underline text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-slate-800 focus:ring-blue-500"
                    >
                        Déconnexion
                    </Link>
                </div>
            </div>
        </form>
    </AuthenticationCard>
</template>
