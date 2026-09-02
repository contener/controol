<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PermissionsGrid from './Partials/PermissionsGrid.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    groupesPermissions: Object,
    roles: Array,
    presets: Object,
});

const form = useForm({
    name: '',
    email: '',
    telephone: '',
    password: '',
    admin_role_label: '',
    permissions: [],
});

// L'étiquette de rôle ne fait que préremplir les cases à cocher — un préréglage de
// confort, jamais une source d'autorisation (le serveur ne stocke que les permissions
// réellement cochées à la soumission).
const appliquerPreset = () => {
    form.permissions = [...(props.presets[form.admin_role_label] ?? [])];
};

const submit = () => {
    form.post(route('admin.administrateurs.store'));
};
</script>

<template>
    <AppLayout title="Créer un administrateur">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Créer un administrateur</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <form class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="name" value="Nom complet *" />
                            <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.name" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email *" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.email" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="telephone" value="Numéro de téléphone" />
                            <TextInput id="telephone" v-model="form.telephone" class="mt-1 block w-full" />
                            <InputError :message="form.errors.telephone" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="password" value="Mot de passe temporaire *" />
                            <TextInput id="password" v-model="form.password" type="text" class="mt-1 block w-full" required />
                            <InputError :message="form.errors.password" class="mt-2" />
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">À communiquer à l'administrateur ; il pourra le changer depuis son profil.</p>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="admin_role_label" value="Rôle *" />
                        <SelectInput id="admin_role_label" v-model="form.admin_role_label" class="mt-1 block w-full sm:w-64" @change="appliquerPreset">
                            <option value="" disabled>Sélectionner un rôle</option>
                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                        </SelectInput>
                        <InputError :message="form.errors.admin_role_label" class="mt-2" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Préremplit les permissions ci-dessous ; vous pouvez ensuite les ajuster librement.</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Permissions</h3>
                        <PermissionsGrid v-model="form.permissions" :groupes-permissions="groupesPermissions" />
                        <InputError :message="form.errors.permissions" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Créer l'administrateur</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
