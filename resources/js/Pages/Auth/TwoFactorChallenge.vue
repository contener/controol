<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value.focus();
        form.code = '';
    } else {
        codeInput.value.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
    <Head title="Vérification en deux étapes" />

    <AuthenticationCard>
        <template #logo>
            <AuthenticationCardLogo />
        </template>

        <h1 class="text-center text-lg font-semibold text-slate-900 dark:text-slate-100 mb-1">Vérification en deux étapes</h1>
        <p class="text-center text-sm text-slate-500 dark:text-slate-400 mb-6">
            <template v-if="! recovery">
                Confirmez l'accès à votre compte en saisissant le code fourni par votre application d'authentification.
            </template>
            <template v-else>
                Confirmez l'accès à votre compte en saisissant l'un de vos codes de secours.
            </template>
        </p>

        <form @submit.prevent="submit">
            <div v-if="! recovery">
                <InputLabel for="code" value="Code" />
                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full"
                    autofocus
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.code" />
            </div>

            <div v-else>
                <InputLabel for="recovery_code" value="Code de secours" />
                <TextInput
                    id="recovery_code"
                    ref="recoveryCodeInput"
                    v-model="form.recovery_code"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="one-time-code"
                />
                <InputError class="mt-2" :message="form.errors.recovery_code" />
            </div>

            <div class="flex items-center justify-between mt-6">
                <button type="button" class="text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 underline cursor-pointer" @click.prevent="toggleRecovery">
                    <template v-if="! recovery">Utiliser un code de secours</template>
                    <template v-else>Utiliser un code d'authentification</template>
                </button>

                <PrimaryButton class="ms-4" :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'Connexion...' : 'Se connecter' }}
                </PrimaryButton>
            </div>
        </form>
    </AuthenticationCard>
</template>
