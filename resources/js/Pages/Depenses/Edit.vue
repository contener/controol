<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DepenseForm from './Partials/DepenseForm.vue';

const props = defineProps({
    depense: Object,
    categories: Array,
});

const form = useForm({
    categorie: props.depense.categorie,
    montant: props.depense.montant,
    description: props.depense.description,
    date_depense: props.depense.date_depense,
});

const submit = () => {
    form.put(route('depenses.update', props.depense.id));
};
</script>

<template>
    <AppLayout title="Modifier la dépense">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Modifier la dépense</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <DepenseForm :form="form" :processing="form.processing" :categories="categories" @submit="submit" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
