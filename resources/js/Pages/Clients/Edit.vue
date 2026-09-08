<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ClientForm from './Partials/ClientForm.vue';

const props = defineProps({
    client: Object,
});

const form = useForm({
    nom: props.client.nom,
    email: props.client.email,
    telephone: props.client.telephone,
    adresse: props.client.adresse,
    ville: props.client.ville,
    pays: props.client.pays,
    numero_fiscal: props.client.numero_fiscal,
    notes: props.client.notes,
    etiquette: props.client.etiquette,
});

const submit = () => {
    form.put(route('clients.update', props.client.id));
};
</script>

<template>
    <AppLayout title="Modifier le client">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Modifier le client</h2>
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
