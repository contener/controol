<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
    form: Object,
    processing: Boolean,
    boutique: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['submit']);

const onFile = (event, champ, form) => {
    form[champ] = event.target.files[0] ?? null;
};
</script>

<template>
    <form class="space-y-8" @submit.prevent="emit('submit')">
        <section>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Informations générales</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <InputLabel for="nom" value="Nom de la boutique *" />
                    <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="form.errors.nom" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="categorie" value="Catégorie" />
                    <TextInput id="categorie" v-model="form.categorie" type="text" class="mt-1 block w-full" placeholder="Informatique, Vêtements..." />
                    <InputError :message="form.errors.categorie" class="mt-2" />
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
                    <InputLabel for="adresse" value="Adresse" />
                    <TextInput id="adresse" v-model="form.adresse" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.adresse" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="telephone" value="Téléphone" />
                    <TextInput id="telephone" v-model="form.telephone" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.telephone" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="whatsapp" value="Numéro WhatsApp" />
                    <TextInput id="whatsapp" v-model="form.whatsapp" type="text" class="mt-1 block w-full" placeholder="+237600000000" />
                    <InputError :message="form.errors.whatsapp" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                    <InputError :message="form.errors.email" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="devise" value="Devise" />
                    <TextInput id="devise" v-model="form.devise" type="text" class="mt-1 block w-full" maxlength="3" />
                    <InputError :message="form.errors.devise" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="taux_tva_defaut" value="Taux de TVA par défaut (%)" />
                    <TextInput id="taux_tva_defaut" v-model="form.taux_tva_defaut" type="number" step="0.01" class="mt-1 block w-full" />
                    <InputError :message="form.errors.taux_tva_defaut" class="mt-2" />
                </div>
            </div>

            <div class="mt-6">
                <InputLabel for="description" value="Description" />
                <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.description" class="mt-2" />
            </div>
        </section>

        <section class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Apparence</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <InputLabel for="logo" value="Logo" />
                    <img v-if="boutique?.logo_path" :src="`/storage/${boutique.logo_path}`" class="h-16 w-16 object-cover rounded mb-2" alt="Logo actuel">
                    <input id="logo" type="file" accept="image/*" class="mt-1 block w-full text-sm dark:text-gray-300" @change="onFile($event, 'logo', form)">
                    <InputError :message="form.errors.logo" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="banniere" value="Bannière" />
                    <img v-if="boutique?.banniere_path" :src="`/storage/${boutique.banniere_path}`" class="h-16 w-full object-cover rounded mb-2" alt="Bannière actuelle">
                    <input id="banniere" type="file" accept="image/*" class="mt-1 block w-full text-sm dark:text-gray-300" @change="onFile($event, 'banniere', form)">
                    <InputError :message="form.errors.banniere" class="mt-2" />
                </div>
            </div>
        </section>

        <section class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Réseaux sociaux (pour le partage)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div>
                    <InputLabel for="facebook_url" value="Lien Facebook" />
                    <TextInput id="facebook_url" v-model="form.facebook_url" type="url" class="mt-1 block w-full" />
                    <InputError :message="form.errors.facebook_url" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="instagram_url" value="Lien Instagram" />
                    <TextInput id="instagram_url" v-model="form.instagram_url" type="url" class="mt-1 block w-full" />
                    <InputError :message="form.errors.instagram_url" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="telegram_url" value="Lien Telegram" />
                    <TextInput id="telegram_url" v-model="form.telegram_url" type="url" class="mt-1 block w-full" />
                    <InputError :message="form.errors.telegram_url" class="mt-2" />
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-4">
            <PrimaryButton :disabled="processing">Enregistrer</PrimaryButton>
        </div>
    </form>
</template>
