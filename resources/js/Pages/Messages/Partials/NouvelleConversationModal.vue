<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    clients: { type: Array, default: () => [] },
});
const emit = defineEmits(['close']);

const page = usePage();
const lienConversation = computed(() => page.props.flash?.lien_conversation);

const form = useForm({ client_id: null, contenu: '' });

watch(() => props.show, (visible) => {
    if (visible) {
        form.reset();
        form.clearErrors();
    }
});

const envoyer = () => {
    form.post(route('messages.store'), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

const copierLien = () => {
    if (lienConversation.value) {
        navigator.clipboard?.writeText(lienConversation.value);
    }
};
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Nouvelle conversation</template>
        <template #content>
            <div>
                <InputLabel for="client_id" value="Client" />
                <SelectInput id="client_id" v-model="form.client_id" class="mt-1 block w-full">
                    <option :value="null" disabled>Sélectionner un client</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.nom }}</option>
                </SelectInput>
                <InputError :message="form.errors.client_id" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="contenu" value="Votre message" />
                <textarea id="contenu" v-model="form.contenu" rows="3" class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.contenu" class="mt-2" />
            </div>

            <div v-if="lienConversation" class="mt-4 rounded-md bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-800 flex items-center justify-between gap-2">
                <span>Lien à transmettre manuellement au client pour qu'il puisse répondre.</span>
                <button type="button" class="shrink-0 font-medium underline" @click="copierLien">Copier</button>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="envoyer">
                Envoyer
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
