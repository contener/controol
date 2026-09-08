<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ClientForm from './Partials/ClientForm.vue';

const form = useForm({
    nom: '',
    email: '',
    telephone: '',
    adresse: '',
    ville: '',
    pays: '',
    numero_fiscal: '',
    notes: '',
    etiquette: 'prospect',
});

const submit = () => {
    form.post(route('clients.store'));
};
</script>

<template>
    <AppLayout title="Nouveau client">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Nouveau client</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <ClientForm :form="form" :processing="form.processing" @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
