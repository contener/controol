<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    boutiqueSlug: { type: String, required: true },
});
const emit = defineEmits(['close']);

const form = useForm({
    type: 'produit',
    nom: '',
    description: '',
    prix_vente: '',
    unite: 'pièce',
    categorie: '',
    photo: null,
});

watch(() => props.show, (visible) => {
    if (!visible) {
        return;
    }
    form.reset();
    form.clearErrors();
});

const onPhoto = (event) => {
    form.photo = event.target.files[0] ?? null;
};

const enregistrer = () => {
    form.post(route('public.boutique.produits.store', props.boutiqueSlug), {
        forceFormData: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <DialogModal :show="show" @close="$emit('close')">
        <template #title>Ajouter un nouveau produit</template>
        <template #content>
            <p class="text-sm text-slate-500 mb-4">
                Ce produit sera immédiatement visible dans votre boutique. Vous pourrez
                masquer sa visibilité à tout moment depuis Produits &amp; Services.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="ap_type" value="Type *" />
                    <SelectInput id="ap_type" v-model="form.type" class="mt-1 block w-full">
                        <option value="produit">Produit</option>
                        <option value="service">Service</option>
                    </SelectInput>
                    <InputError :message="form.errors.type" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="ap_nom" value="Nom *" />
                    <TextInput id="ap_nom" v-model="form.nom" type="text" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.nom" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="ap_prix_vente" value="Prix de vente *" />
                    <TextInput id="ap_prix_vente" v-model="form.prix_vente" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.prix_vente" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="ap_unite" value="Unité *" />
                    <TextInput id="ap_unite" v-model="form.unite" type="text" class="mt-1 block w-full" placeholder="pièce, heure, kg..." />
                    <InputError :message="form.errors.unite" class="mt-2" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="ap_categorie" value="Catégorie (optionnel)" />
                    <TextInput id="ap_categorie" v-model="form.categorie" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.categorie" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="ap_description" value="Description (optionnel)" />
                <textarea id="ap_description" v-model="form.description" rows="2" class="mt-1 block w-full border-slate-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.description" class="mt-2" />
            </div>

            <div class="mt-4">
                <InputLabel for="ap_photo" value="Photo (optionnel)" />
                <input id="ap_photo" type="file" accept="image/*" class="mt-1 block w-full text-sm" @change="onPhoto">
                <InputError :message="form.errors.photo" class="mt-2" />
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="$emit('close')">Annuler</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="enregistrer">
                Ajouter à ma boutique
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
