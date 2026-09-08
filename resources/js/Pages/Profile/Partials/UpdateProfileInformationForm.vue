<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    user: Object,
});

const form = useForm({
    _method: 'PUT',
    name: props.user.name,
    email: props.user.email,
    date_naissance: props.user.date_naissance,
    ville: props.user.ville,
    telephone: props.user.telephone,
    whatsapp: props.user.whatsapp,
    photo: null,
});

const verificationLinkSent = ref(null);
const photoPreview = ref(null);
const photoInput = ref(null);

const updateProfileInformation = () => {
    if (photoInput.value) {
        form.photo = photoInput.value.files[0];
    }

    form.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => clearPhotoFileInput(),
    });
};

const sendEmailVerification = () => {
    verificationLinkSent.value = true;
};

const selectNewPhoto = () => {
    photoInput.value.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];

    if (! photo) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        photoPreview.value = e.target.result;
    };

    reader.readAsDataURL(photo);
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            clearPhotoFileInput();
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value?.value) {
        photoInput.value.value = null;
    }
};
</script>

<template>
    <FormSection @submitted="updateProfileInformation">
        <template #title>
            Informations du profil
        </template>

        <template #description>
            Vos informations personnelles et vos coordonnées.
        </template>

        <template #form>
            <!-- Profile Photo -->
            <div v-if="$page.props.jetstream.managesProfilePhotos" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input
                    id="photo"
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    @change="updatePhotoPreview"
                >

                <InputLabel for="photo" value="Photo" />

                <!-- Current Profile Photo -->
                <div v-show="! photoPreview" class="mt-2">
                    <img :src="user.profile_photo_url" :alt="user.name" class="rounded-full size-20 object-cover">
                </div>

                <!-- New Profile Photo Preview -->
                <div v-show="photoPreview" class="mt-2">
                    <span
                        class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                        :style="'background-image: url(\'' + photoPreview + '\');'"
                    />
                </div>

                <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                    Changer la photo
                </SecondaryButton>

                <SecondaryButton
                    v-if="user.profile_photo_path"
                    type="button"
                    class="mt-2"
                    @click.prevent="deletePhoto"
                >
                    Supprimer la photo
                </SecondaryButton>

                <InputError :message="form.errors.photo" class="mt-2" />
            </div>

            <!-- Name -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="name" value="Nom complet" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                />
                <InputError :message="form.errors.name" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="email" value="Adresse email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                />
                <InputError :message="form.errors.email" class="mt-2" />

                <div v-if="$page.props.jetstream.hasEmailVerification && user.email_verified_at === null">
                    <p class="text-sm mt-2 dark:text-slate-300">
                        Votre adresse email n'est pas vérifiée.

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="underline text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            @click.prevent="sendEmailVerification"
                        >
                            Cliquez ici pour renvoyer l'email de vérification.
                        </Link>
                    </p>

                    <div v-show="verificationLinkSent" class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                        Un nouveau lien de vérification a été envoyé à votre adresse email.
                    </div>
                </div>
            </div>

            <!-- Date de naissance -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="date_naissance" value="Date de naissance" />
                <TextInput
                    id="date_naissance"
                    v-model="form.date_naissance"
                    type="date"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.date_naissance" class="mt-2" />
            </div>

            <!-- Ville -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="ville" value="Ville" />
                <TextInput
                    id="ville"
                    v-model="form.ville"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.ville" class="mt-2" />
            </div>

            <!-- Téléphone -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="telephone" value="Numéro de téléphone" />
                <TextInput
                    id="telephone"
                    v-model="form.telephone"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.telephone" class="mt-2" />
            </div>

            <!-- WhatsApp -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="whatsapp" value="Numéro WhatsApp" />
                <TextInput
                    id="whatsapp"
                    v-model="form.whatsapp"
                    type="text"
                    class="mt-1 block w-full"
                    placeholder="+237600000000"
                />
                <InputError :message="form.errors.whatsapp" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Enregistré.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Enregistrer
            </PrimaryButton>
        </template>
    </FormSection>
</template>
