<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();

            passwordInput.value.focus();
        },
    });
};
</script>

<template>
    <Head title="Zone sécurisée" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <h1 class="text-center text-lg font-semibold text-slate-900 dark:text-slate-100 mb-1">Zone sécurisée</h1>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400 mb-6">
            Ceci est une zone sécurisée de l'application. Merci de confirmer votre mot de passe avant de continuer.
        </p>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Mot de passe" />
                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex justify-end mt-6">
                <PrimaryButton class="w-full justify-center" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Confirmation...' : 'Confirmer' }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
