<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    form: Object,
    processing: Boolean,
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <InputLabel for="nom" value="Nom *" />
                <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required autofocus />
                <InputError :message="form.errors.nom" class="mt-2" />
            </div>

            <div>
                <InputLabel for="etiquette" value="Étiquette *" />
                <SelectInput id="etiquette" v-model="form.etiquette" class="mt-1 block w-full">
                    <option value="prospect">Prospect</option>
                    <option value="client">Client</option>
                </SelectInput>
                <InputError :message="form.errors.etiquette" class="mt-2" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                <InputError :message="form.errors.email" class="mt-2" />
            </div>

            <div>
                <InputLabel for="telephone" value="Téléphone" />
                <TextInput id="telephone" v-model="form.telephone" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.telephone" class="mt-2" />
            </div>

            <div>
                <InputLabel for="adresse" value="Adresse" />
                <TextInput id="adresse" v-model="form.adresse" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.adresse" class="mt-2" />
            </div>

            <div>
                <InputLabel for="ville" value="Ville" />
                <TextInput id="ville" v-model="form.ville" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.ville" class="mt-2" />
            </div>

            <div>
                <InputLabel for="pays" value="Pays" />
                <TextInput id="pays" v-model="form.pays" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.pays" class="mt-2" />
            </div>

            <div>
                <InputLabel for="numero_fiscal" value="Numéro fiscal / RCCM" />
                <TextInput id="numero_fiscal" v-model="form.numero_fiscal" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.numero_fiscal" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="notes" value="Notes" />
            <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
            <InputError :message="form.errors.notes" class="mt-2" />
        </div>

        <div class="flex items-center justify-end gap-4">
            <PrimaryButton :disabled="processing">Enregistrer</PrimaryButton>
        </div>
    </form>
</template>
