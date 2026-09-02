<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    form: Object,
    processing: Boolean,
    categories: Array,
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <InputLabel for="categorie" value="Catégorie *" />
                <SelectInput id="categorie" v-model="form.categorie" class="mt-1 block w-full">
                    <option value="" disabled>Sélectionner...</option>
                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                </SelectInput>
                <InputError :message="form.errors.categorie" class="mt-2" />
            </div>

            <div>
                <InputLabel for="montant" value="Montant *" />
                <TextInput id="montant" v-model="form.montant" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                <InputError :message="form.errors.montant" class="mt-2" />
            </div>

            <div>
                <InputLabel for="date_depense" value="Date *" />
                <TextInput id="date_depense" v-model="form.date_depense" type="date" class="mt-1 block w-full" required />
                <InputError :message="form.errors.date_depense" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="description" value="Description" />
            <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
            <InputError :message="form.errors.description" class="mt-2" />
        </div>

        <div class="flex items-center justify-end gap-4">
            <PrimaryButton :disabled="processing">Enregistrer</PrimaryButton>
        </div>
    </form>
</template>
