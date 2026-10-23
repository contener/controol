<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    autorise: Boolean,
    permissionsDisponibles: { type: Array, default: () => [] },
    collaborateurs: { type: Array, default: () => [] },
});

// Libellés d'affichage des 7 permissions (voir Boutique::PERMISSIONS_COLLABORATEUR) --
// seule la liste des clés vient du backend (permissionsDisponibles), les libellés
// restent ici car c'est un texte d'affichage, pas une règle métier.
const libelles = {
    acces_dashboard: 'Tableau de bord',
    acces_clients: 'Clients',
    acces_produits: 'Produits & Stock',
    acces_factures: 'Factures',
    acces_depenses: 'Dépenses',
    acces_audience: 'Audience',
    acces_messagerie: 'Messagerie',
};

const permissionsVides = () => Object.fromEntries(props.permissionsDisponibles.map((cle) => [cle, false]));

const form = useForm({
    name: '',
    email: '',
    telephone: '',
    password: '',
    ...permissionsVides(),
});

const ajouter = () => {
    form.post(route('boutique.collaborateurs.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const retirer = (collaborateur) => {
    if (confirm(`Retirer l'accès de ${collaborateur.name} à cette boutique ?`)) {
        router.delete(route('boutique.collaborateurs.destroy', collaborateur.id), { preserveScroll: true });
    }
};

// Formulaire de permissions par collaborateur déjà créé -- distinct de `form`
// (création), un par ligne, pour pouvoir modifier l'accès d'un collaborateur sans
// toucher aux autres ni au formulaire de création.
const formsPermissions = {};
const formPermissions = (collaborateur) => {
    if (!formsPermissions[collaborateur.id]) {
        formsPermissions[collaborateur.id] = useForm({ ...collaborateur.permissions });
    }

    return formsPermissions[collaborateur.id];
};

const enregistrerPermissions = (collaborateur) => {
    formPermissions(collaborateur).patch(route('boutique.collaborateurs.update', collaborateur.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Équipe">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🔑 Équipe</h2>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Créez des comptes pour les personnes qui vous aident à gérer cette boutique au quotidien. Cochez
                    vous-même les fonctionnalités auxquelles chacun a accès — rien n'est donné automatiquement. La
                    boutique elle-même, l'abonnement et la gestion de l'équipe restent réservés à vous seul.
                </p>

                <div class="relative">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6 space-y-6" :class="{ 'blur-sm select-none pointer-events-none': !autorise }">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Ajouter un collaborateur</h3>
                            <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="ajouter">
                                <div>
                                    <InputLabel for="name" value="Nom *" />
                                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.name" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="E-mail *" />
                                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.email" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="telephone" value="Téléphone" />
                                    <TextInput id="telephone" v-model="form.telephone" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.telephone" class="mt-1" />
                                </div>
                                <div>
                                    <InputLabel for="password" value="Mot de passe *" />
                                    <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" />
                                    <InputError :message="form.errors.password" class="mt-1" />
                                </div>

                                <div class="sm:col-span-2">
                                    <InputLabel value="Accès accordé" />
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Rien n'est coché par défaut — choisissez ce que cette personne peut utiliser.</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        <label v-for="cle in permissionsDisponibles" :key="cle" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" v-model="form[cle]" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500">
                                            {{ libelles[cle] ?? cle }}
                                        </label>
                                    </div>
                                </div>

                                <div class="sm:col-span-2 flex justify-end">
                                    <PrimaryButton :disabled="form.processing">Créer le compte</PrimaryButton>
                                </div>
                            </form>
                        </div>

                        <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Collaborateurs actuels</h3>
                            <div v-if="collaborateurs.length === 0" class="text-sm text-slate-400 dark:text-slate-500">Aucun collaborateur pour le moment.</div>
                            <ul v-else class="divide-y divide-slate-100 dark:divide-slate-700">
                                <li v-for="collaborateur in collaborateurs" :key="collaborateur.id" class="py-3 space-y-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">{{ collaborateur.name }}</div>
                                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ collaborateur.email }} · ajouté le {{ collaborateur.ajoute_le }}</div>
                                        </div>
                                        <SecondaryButton class="shrink-0" @click="retirer(collaborateur)">Retirer l'accès</SecondaryButton>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 bg-slate-50 dark:bg-slate-900 rounded-md p-3">
                                        <label v-for="cle in permissionsDisponibles" :key="cle" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" v-model="formPermissions(collaborateur)[cle]" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500">
                                            {{ libelles[cle] ?? cle }}
                                        </label>
                                        <div class="col-span-2 sm:col-span-3 flex justify-end">
                                            <SecondaryButton :disabled="formPermissions(collaborateur).processing" @click="enregistrerPermissions(collaborateur)">
                                                Enregistrer l'accès
                                            </SecondaryButton>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="!autorise" class="absolute inset-0 flex flex-col items-center justify-center text-center gap-2 px-4">
                        <span class="text-2xl">🔒</span>
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                            Débloquez la gestion d'équipe avec le plan Basique (5 000 FCFA/mois)
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md">
                            Créez des comptes pour vos employés et donnez-leur accès à la gestion de votre boutique.
                        </p>
                        <Link :href="route('abonnement.index')">
                            <PrimaryButton class="mt-1">🚀 Passer au plan Basic</PrimaryButton>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
