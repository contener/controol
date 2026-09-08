<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DepenseForm from './Partials/DepenseForm.vue';

defineProps({
    categories: Array,
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    categorie: '',
    montant: '',
    description: '',
    date_depense: today,
});

const submit = () => {
    form.post(route('depenses.store'));
};
</script>

<template>
    <AppLayout title="Nouvelle dépense">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Nouvelle dépense</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-6">
                    <DepenseForm :form="form" :processing="form.processing" :categories="categories" @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
