<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    modeles: Array,
    parametres: Object,
    statistiques: Object,
    permissionsNotifications: Object,
});

const formsModeles = reactive(
    Object.fromEntries(props.modeles.map((m) => [m.id, useForm({
        titre: m.titre,
        message: m.message,
        actif: m.actif,
    })])),
);

const enregistrerModele = (modele) => {
    formsModeles[modele.id].patch(route('admin.notifications.modeles.update', modele.id), { preserveScroll: true });
};

const variablesDisponibles = ['nom_utilisateur', 'jours_restants', 'date_fin', 'prix_normal', 'prix_promotionnel']
    .map((v) => `{{${v}}}`)
    .join(', ');

const formParametres = useForm({
    heure_notification: props.parametres.heure_notification?.slice(0, 5) ?? '08:00',
    fuseau: props.parametres.fuseau ?? 'Africa/Douala',
});

const enregistrerParametres = () => {
    formParametres.patch(route('admin.notifications.parametres.update'), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Administration — Notifications">
        <template #header>
            <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">🔔 Notifications</h2>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Essais actifs</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.actifs }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Expirant aujourd'hui</div>
                        <div class="mt-1 text-2xl font-semibold text-yellow-600 dark:text-yellow-400">{{ statistiques.expirant_aujourdhui }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Essais expirés</div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ statistiques.expires }}</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-4">
                        <div class="text-xs text-slate-500 dark:text-slate-400 uppercase">Conversions</div>
                        <div class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">{{ statistiques.convertis }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">Heure d'envoi des rappels quotidiens</h3>
                    <form class="flex flex-wrap items-end gap-4" @submit.prevent="enregistrerParametres">
                        <div>
                            <InputLabel for="heure_notification" value="Heure" />
                            <TextInput id="heure_notification" v-model="formParametres.heure_notification" type="time" class="mt-1" :disabled="!permissionsNotifications.envoyer" />
                            <InputError :message="formParametres.errors.heure_notification" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="fuseau" value="Fuseau horaire" />
                            <TextInput id="fuseau" v-model="formParametres.fuseau" type="text" class="mt-1" :disabled="!permissionsNotifications.envoyer" />
                            <InputError :message="formParametres.errors.fuseau" class="mt-2" />
                        </div>
                        <PrimaryButton v-if="permissionsNotifications.envoyer" :disabled="formParametres.processing">Enregistrer</PrimaryButton>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Messages de rappel d'essai</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        Variables disponibles : {{ variablesDisponibles }}
                    </p>

                    <div class="space-y-4">
                        <div v-for="modele in modeles" :key="modele.id" class="border border-slate-200 dark:border-slate-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">Jour {{ modele.jour }}</span>
                                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <Checkbox v-model:checked="formsModeles[modele.id].actif" :disabled="!permissionsNotifications.envoyer" />
                                    Actif
                                </label>
                            </div>

                            <InputLabel :for="`titre-${modele.id}`" value="Titre" />
                            <TextInput :id="`titre-${modele.id}`" v-model="formsModeles[modele.id].titre" type="text" class="mt-1 block w-full" :disabled="!permissionsNotifications.envoyer" />
                            <InputError :message="formsModeles[modele.id].errors.titre" class="mt-2" />

                            <InputLabel :for="`message-${modele.id}`" value="Message" class="mt-3" />
                            <textarea
                                :id="`message-${modele.id}`"
                                v-model="formsModeles[modele.id].message"
                                rows="4"
                                class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                                :disabled="!permissionsNotifications.envoyer"
                            />
                            <InputError :message="formsModeles[modele.id].errors.message" class="mt-2" />

                            <div v-if="permissionsNotifications.envoyer" class="flex justify-end mt-3">
                                <PrimaryButton :disabled="formsModeles[modele.id].processing" @click="enregistrerModele(modele)">Enregistrer</PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
