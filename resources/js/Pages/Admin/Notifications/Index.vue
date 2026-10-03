<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../Partials/AdminSubNav.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import RelanceWhatsappModal from '../Utilisateurs/Partials/RelanceWhatsappModal.vue';

const props = defineProps({
    modeles: Array,
    parametres: Object,
    statistiques: Object,
    permissionsNotifications: Object,
    essais: Object,
    filtresEssais: Object,
    permissionsWhatsapp: Object,
    modelesWhatsappEssai: { type: Array, default: () => [] },
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

const statutLabels = {
    en_cours: 'Essai en cours',
    converti: 'Converti',
    expire: 'Essai expiré',
    annule: 'Annulé',
};

const statutClasses = {
    en_cours: 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300',
    converti: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    expire: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
    annule: 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300',
};

const statutEssai = ref(props.filtresEssais.statutEssai ?? '');
const rechercheEssai = ref(props.filtresEssais.rechercheEssai ?? '');

const appliquerFiltresEssais = () => {
    router.get(route('admin.notifications.index'), {
        statutEssai: statutEssai.value,
        rechercheEssai: rechercheEssai.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

watch(statutEssai, appliquerFiltresEssais);

let timeoutIdEssai = null;
watch(rechercheEssai, () => {
    clearTimeout(timeoutIdEssai);
    timeoutIdEssai = setTimeout(appliquerFiltresEssais, 300);
});

// Le modèle de relance se détermine automatiquement selon que l'utilisateur a déjà créé
// une boutique ou non -- jamais à l'admin de choisir manuellement à chaque fois quel
// message correspond à quelle situation.
const essaiARelancer = ref(null);
const cibleRelance = computed(() => (essaiARelancer.value
    ? {
        id: essaiARelancer.value.user_id,
        nom: essaiARelancer.value.nom,
        whatsapp: essaiARelancer.value.whatsapp,
        joursRestants: essaiARelancer.value.jours_restants,
    }
    : null));
const modeleCleRelance = computed(() => (essaiARelancer.value?.a_boutique
    ? 'essai_relance_avec_boutique'
    : 'essai_relance_sans_boutique'));
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

                <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Utilisateurs en essai</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                        Statut, jours restants et relance manuelle — le message proposé s'adapte
                        automatiquement : invitation à créer une boutique si ce n'est pas encore fait,
                        ou invitation à s'abonner si la boutique existe déjà.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 mb-4">
                        <TextInput v-model="rechercheEssai" placeholder="Rechercher par nom ou email..." class="flex-1" />
                        <SelectInput v-model="statutEssai" class="sm:w-56">
                            <option value="">Tous les statuts</option>
                            <option value="en_cours">Essai en cours</option>
                            <option value="expire">Essai expiré</option>
                            <option value="converti">Converti</option>
                            <option value="annule">Annulé</option>
                        </SelectInput>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Utilisateur</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Boutique</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Statut</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Jours restants</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Dernier rappel</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-if="essais.data.length === 0">
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">Aucun utilisateur trouvé.</td>
                                </tr>
                                <tr v-for="essai in essais.data" :key="essai.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ essai.nom }}</div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ essai.email }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full" :class="essai.a_boutique ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300'">
                                            {{ essai.a_boutique ? 'Oui' : 'Pas encore' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[essai.statut]">
                                            {{ statutLabels[essai.statut] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-700 dark:text-slate-300">
                                        <span v-if="essai.statut === 'en_cours'" class="font-semibold" :class="essai.jours_restants <= 1 ? 'text-red-600 dark:text-red-400' : ''">
                                            {{ essai.jours_restants }} jour{{ essai.jours_restants > 1 ? 's' : '' }}
                                        </span>
                                        <span v-else class="text-slate-400 dark:text-slate-500">—</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                        {{ essai.dernier_jour_notifie ? `Jour ${essai.dernier_jour_notifie}` : 'Jamais' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-sm">
                                        <button
                                            v-if="permissionsWhatsapp?.contacter"
                                            class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 font-medium"
                                            @click="essaiARelancer = essai"
                                        >
                                            Relancer
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <RelanceWhatsappModal
                        :show="essaiARelancer !== null"
                        :cible="cibleRelance"
                        type="utilisateur"
                        :modeles="modelesWhatsappEssai"
                        :modele-cle-initiale="modeleCleRelance"
                        @close="essaiARelancer = null"
                        @envoye="essaiARelancer = null"
                    />

                    <div v-if="essais.links.length > 3" class="flex flex-wrap gap-1 mt-4">
                        <Link
                            v-for="(link, index) in essais.links"
                            :key="index"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            class="px-3 py-1 text-sm rounded border"
                            :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            :disabled="!link.url"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
