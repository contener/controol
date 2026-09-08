<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const form = useForm({
    nom: '', telephone: '', whatsapp: '', email: '', ville: '', entreprise: '', source: '', notes: '',
});

watch(() => props.show, (visible) => {
    if (visible) {
        form.reset();
        form.clearErrors();
    }
});

const enregistrer = () => {
    form.post(route('admin.contacts.store'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Nouveau contact</template>
        <template #content>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="nom" value="Nom" />
                    <TextInput id="nom" v-model="form.nom" class="mt-1 block w-full" />
                    <InputError :message="form.errors.nom" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="whatsapp" value="WhatsApp" />
                    <TextInput id="whatsapp" v-model="form.whatsapp" class="mt-1 block w-full" placeholder="+237 6XX XXX XXX" />
                    <InputError :message="form.errors.whatsapp" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="telephone" value="Téléphone" />
                    <TextInput id="telephone" v-model="form.telephone" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="email" value="E-mail" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="ville" value="Ville" />
                    <TextInput id="ville" v-model="form.ville" class="mt-1 block w-full" />
                </div>
                <div>
                    <InputLabel for="entreprise" value="Entreprise" />
                    <TextInput id="entreprise" v-model="form.entreprise" class="mt-1 block w-full" />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="source" value="Source" />
                    <TextInput id="source" v-model="form.source" class="mt-1 block w-full" placeholder="Ex. : Salon, recommandation..." />
                </div>
                <div class="sm:col-span-2">
                    <InputLabel for="notes" value="Notes" />
                    <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                    <InputError :message="form.errors.notes" class="mt-2" />
                </div>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="enregistrer">
                Enregistrer
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
