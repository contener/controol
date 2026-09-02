<script setup>
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import PermissionsGrid from './Partials/PermissionsGrid.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    administrateur: Object,
    groupesPermissions: Object,
    roles: Array,
    presets: Object,
    audits: Array,
});

const form = useForm({
    name: props.administrateur.name,
    email: props.administrateur.email,
    telephone: props.administrateur.telephone ?? '',
    password: '',
    admin_role_label: props.administrateur.admin_role_label ?? '',
    permissions: [...props.administrateur.permissions],
});

const appliquerPreset = () => {
    form.permissions = [...(props.presets[form.admin_role_label] ?? [])];
};

const submit = () => {
    form.put(route('admin.administrateurs.update', props.administrateur.id));
};

const basculer = () => {
    const action = props.administrateur.est_actif ? 'désactiver' : 'réactiver';
    if (confirm(`Confirmer : ${action} "${props.administrateur.name}" ?`)) {
        router.patch(route('admin.administrateurs.basculer', props.administrateur.id));
    }
};

const formatDateHeure = (d) => new Date(d).toLocaleString('fr-FR');

const actionLabels = {
    administrateur_cree: 'Création',
    administrateur_modifie: 'Modification des permissions/rôle',
    administrateur_desactive: 'Désactivation',
    administrateur_reactive: 'Réactivation',
};
</script>

<template>
    <AppLayout :title="`Modifier ${administrateur.name}`">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Modifier {{ administrateur.name }}</h2>
                <span class="px-2 py-1 text-xs font-medium rounded-full" :class="administrateur.est_actif ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'">
                    {{ administrateur.est_actif ? 'Actif' : 'Inactif' }}
                </span>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Statut du compte</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Un administrateur désactivé perd immédiatement l'accès à l'espace d'administration, sans que son historique soit supprimé.
                        </p>
                    </div>
                    <SecondaryButton @click="basculer">{{ administrateur.est_actif ? 'Désactiver' : 'Réactiver' }}</SecondaryButton>
                </div>

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
                            <InputLabel for="password" value="Nouveau mot de passe" />
                            <TextInput id="password" v-model="form.password" type="text" class="mt-1 block w-full" placeholder="Laisser vide pour ne pas changer" />
                            <InputError :message="form.errors.password" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="admin_role_label" value="Rôle *" />
                        <SelectInput id="admin_role_label" v-model="form.admin_role_label" class="mt-1 block w-full sm:w-64" @change="appliquerPreset">
                            <option value="" disabled>Sélectionner un rôle</option>
                            <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                        </SelectInput>
                        <InputError :message="form.errors.admin_role_label" class="mt-2" />
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Permissions</h3>
                        <PermissionsGrid v-model="form.permissions" :groupes-permissions="groupesPermissions" />
                        <InputError :message="form.errors.permissions" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Historique</h3>
                    <div v-if="audits.length === 0" class="text-sm text-gray-400 dark:text-gray-500">Aucune action enregistrée pour le moment.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="audit in audits" :key="audit.id" class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                            <span class="text-gray-700 dark:text-gray-300">
                                {{ actionLabels[audit.action] ?? audit.action }}
                                <span class="text-gray-400 dark:text-gray-500">par {{ audit.admin?.name ?? '—' }}</span>
                            </span>
                            <span class="text-gray-400 dark:text-gray-500">{{ formatDateHeure(audit.created_at) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
