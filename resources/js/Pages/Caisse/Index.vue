<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DialogModal from '@/Components/DialogModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import TextInput from '@/Components/TextInput.vue';

// Caisse de la boutique (2026-11-17) : solde, ajout/retrait d'argent et journal.
// Les ventes encaissées (factures payées, avances de reçus) et les dépenses y
// arrivent automatiquement -- voir App\Services\CaisseService.
const props = defineProps({
    solde: Number,
    devise: String,
    periode: Object,
    mouvements: Object,
    filtres: Object,
    caisse_ouverte_le: String,
});

const sens = ref(props.filtres.sens ?? '');
const origine = ref(props.filtres.origine ?? '');
const debut = ref(props.filtres.debut ?? '');
const fin = ref(props.filtres.fin ?? '');

watch([sens, origine, debut, fin], () => {
    router.get(route('caisse.index'), { sens: sens.value, origine: origine.value, debut: debut.value, fin: fin.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const formatMontant = (montant) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + (props.devise ?? '');
const formatDate = (iso) => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

const origines = {
    apport: { libelle: 'Ajout', classe: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' },
    retrait: { libelle: 'Retrait', classe: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
    vente: { libelle: 'Vente', classe: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
    depense: { libelle: 'Dépense', classe: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' },
};

const filtresActifs = computed(() => sens.value || origine.value || debut.value || fin.value);
const reinitialiser = () => {
    sens.value = '';
    origine.value = '';
    debut.value = '';
    fin.value = '';
};

// Fenêtre d'ajout / de retrait.
const operation = ref(null); // 'apport' | 'retrait' | null
const form = useForm({ montant: '', motif: '' });

const ouvrir = (type) => {
    form.reset();
    form.clearErrors();
    operation.value = type;
};

const valider = () => {
    form.post(route(operation.value === 'apport' ? 'caisse.apport' : 'caisse.retrait'), {
        preserveScroll: true,
        onSuccess: () => {
            operation.value = null;
            form.reset();
        },
    });
};
</script>

<template>
    <AppLayout title="Caisse">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Caisse</h2>
                <div class="flex gap-2">
                    <SecondaryButton @click="ouvrir('retrait')">− Retirer de l'argent</SecondaryButton>
                    <PrimaryButton @click="ouvrir('apport')">+ Ajouter de l'argent</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <!-- Solde et totaux -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-1 rounded-lg p-6 text-white shadow-sm bg-gradient-to-br from-blue-600 to-blue-800">
                        <div class="text-sm text-blue-100">Solde de la caisse</div>
                        <div class="mt-2 text-3xl font-bold" :class="solde < 0 ? 'text-red-200' : ''">{{ formatMontant(solde) }}</div>
                        <div class="mt-2 text-xs text-blue-200">Argent disponible en ce moment</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Entrées {{ filtresActifs && (debut || fin) ? '(période)' : '(total)' }}</div>
                        <div class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">+ {{ formatMontant(periode.entrees) }}</div>
                        <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">Ventes encaissées et ajouts</div>
                    </div>
                    <div class="bg-white dark:bg-slate-800 shadow-sm rounded-lg p-6">
                        <div class="text-sm text-slate-500 dark:text-slate-400">Sorties {{ filtresActifs && (debut || fin) ? '(période)' : '(total)' }}</div>
                        <div class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">− {{ formatMontant(periode.sorties) }}</div>
                        <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">Dépenses et retraits</div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <SelectInput v-model="sens" class="sm:w-40">
                        <option value="">Entrées et sorties</option>
                        <option value="entree">Entrées</option>
                        <option value="sortie">Sorties</option>
                    </SelectInput>
                    <SelectInput v-model="origine" class="sm:w-44">
                        <option value="">Toutes les origines</option>
                        <option v-for="(o, cle) in origines" :key="cle" :value="cle">{{ o.libelle }}</option>
                    </SelectInput>
                    <TextInput v-model="debut" type="date" class="sm:w-44" aria-label="Du" />
                    <TextInput v-model="fin" type="date" class="sm:w-44" aria-label="Au" />
                    <button v-if="filtresActifs" type="button" class="text-sm text-blue-600 dark:text-blue-400 hover:underline" @click="reinitialiser">Réinitialiser</button>
                </div>

                <!-- Journal -->
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Origine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Libellé</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="mouvements.data.length === 0">
                                <td colspan="4" class="px-6 py-10 text-center text-slate-400 dark:text-slate-500">
                                    Aucun mouvement de caisse.
                                    <span class="block mt-1 text-xs">Ajoutez votre fonds de caisse : vos ventes payées et vos dépenses s'enregistreront ensuite automatiquement.</span>
                                </td>
                            </tr>
                            <tr v-for="m in mouvements.data" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ formatDate(m.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="origines[m.origine]?.classe">{{ origines[m.origine]?.libelle ?? m.origine }}</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-800 dark:text-slate-200">
                                    <Link v-if="m.facture" :href="route('factures.show', m.facture.id)" class="hover:underline text-blue-700 dark:text-blue-300">{{ m.libelle }}</Link>
                                    <span v-else>{{ m.libelle }}</span>
                                    <span v-if="m.auteur" class="block text-xs text-slate-400 dark:text-slate-500">par {{ m.auteur.name }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold" :class="m.sens === 'entree' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                    {{ m.sens === 'entree' ? '+' : '−' }} {{ formatMontant(m.montant) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="mouvements.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in mouvements.links"
                        :key="index"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        class="px-3 py-1 text-sm rounded border"
                        :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700'"
                        :disabled="!link.url"
                        preserve-scroll
                    />
                </div>

                <p class="text-xs text-slate-400 dark:text-slate-500">
                    Les factures et reçus payés (et les avances des reçus) entrent dans la caisse ; les dépenses en sortent.
                    Une facture annulée, modifiée ou supprimée ajoute une ligne de correction : l'historique n'est jamais effacé.
                    Les factures et dépenses enregistrées avant le {{ new Date(caisse_ouverte_le).toLocaleDateString('fr-FR') }} ne sont pas comptées.
                </p>
            </div>
        </div>

        <DialogModal :show="operation !== null" max-width="md" @close="operation = null">
            <template #title>{{ operation === 'apport' ? "Ajouter de l'argent dans la caisse" : "Retirer de l'argent de la caisse" }}</template>
            <template #content>
                <form id="form-caisse" class="space-y-4" @submit.prevent="valider">
                    <div>
                        <InputLabel for="montant" :value="`Montant (${devise})`" />
                        <TextInput id="montant" v-model="form.montant" type="number" min="1" step="1" class="mt-1 block w-full" required autofocus />
                        <InputError class="mt-2" :message="form.errors.montant" />
                        <p v-if="operation === 'retrait'" class="mt-1 text-xs text-slate-400 dark:text-slate-500">Disponible : {{ formatMontant(solde) }}</p>
                    </div>
                    <div>
                        <InputLabel for="motif" value="Motif" />
                        <TextInput
                            id="motif"
                            v-model="form.motif"
                            type="text"
                            maxlength="200"
                            class="mt-1 block w-full"
                            :placeholder="operation === 'apport' ? 'Ex. : fonds de caisse, apport personnel' : 'Ex. : dépôt en banque, retrait du gérant'"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.motif" />
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="operation = null">Annuler</SecondaryButton>
                <PrimaryButton class="ms-3" form="form-caisse" type="submit" :disabled="form.processing">
                    {{ operation === 'apport' ? 'Ajouter' : 'Retirer' }}
                </PrimaryButton>
            </template>
        </DialogModal>
    </AppLayout>
</template>
