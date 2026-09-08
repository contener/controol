<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../../Partials/AdminSubNav.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    imports: Object,
});

const LIBELLES_CHAMPS = {
    nom: 'Nom complet', prenom: 'Prénom', nom_famille: 'Nom de famille',
    ville: 'Ville', entreprise: 'Entreprise', poste: 'Poste', adresse: 'Adresse', region: 'Région',
    pays: 'Pays', code_postal: 'Code postal', date_anniversaire: 'Date d\'anniversaire',
    categorie: 'Catégorie', source: 'Source', notes: 'Notes',
};

const etape = ref('upload'); // upload | apercu
const fichier = ref(null);
const enCours = ref(false);
const erreur = ref('');

const analyse = ref(null); // { import_id, en_tetes, mapping_detecte, champs_disponibles, total_lignes, apercu }
const mapping = ref({});
const strategieDoublon = ref('mettre_a_jour');
const lignesIgnorees = ref(new Set());

const selectionnerFichier = (e) => {
    fichier.value = e.target.files[0] ?? null;
};

const analyser = async () => {
    if (!fichier.value) {
        erreur.value = 'Sélectionnez un fichier Excel (.xlsx, .xls) ou CSV.';
        return;
    }

    enCours.value = true;
    erreur.value = '';

    const donnees = new FormData();
    donnees.append('fichier', fichier.value);

    try {
        const { data } = await axios.post(route('admin.contacts.import.analyser'), donnees, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        analyse.value = data;
        mapping.value = { ...data.mapping_detecte };
        lignesIgnorees.value = new Set();
        etape.value = 'apercu';
    } catch (e) {
        erreur.value = e.response?.data?.message ?? "Impossible d'analyser ce fichier.";
    } finally {
        enCours.value = false;
    }
};

const basculerIgnoree = (index) => {
    if (lignesIgnorees.value.has(index)) {
        lignesIgnorees.value.delete(index);
    } else {
        lignesIgnorees.value.add(index);
    }
    lignesIgnorees.value = new Set(lignesIgnorees.value);
};

const confirmer = () => {
    router.post(route('admin.contacts.import.confirmer', analyse.value.import_id), {
        mapping: mapping.value,
        strategie_doublon: strategieDoublon.value,
        lignes_ignorees: Array.from(lignesIgnorees.value),
    });
};

const recommencer = () => {
    etape.value = 'upload';
    fichier.value = null;
    analyse.value = null;
    erreur.value = '';
};

const formatDateHeure = (d) => new Date(d).toLocaleString('fr-FR');
const statutLabels = { en_cours: 'En cours', termine: 'Terminé', echoue: 'Échoué' };
</script>

<template>
    <AppLayout title="Importer des contacts">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Importer des contacts</h2>
                <Link :href="route('admin.contacts.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Retour aux contacts</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div v-if="etape === 'upload'" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <InputLabel value="Fichier Excel ou CSV" />
                    <input type="file" accept=".xlsx,.xls,.csv" class="mt-2 block w-full text-sm text-gray-600 dark:text-gray-300" @change="selectionnerFichier" />
                    <InputError :message="erreur" class="mt-2" />
                    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                        Formats reconnus automatiquement : export Excel/CSV standard, et export Google Contacts
                        (colonnes "First Name", "Phone 1 - Value", "E-mail 1 - Value"...). Encodage et séparateur
                        (virgule ou point-virgule) sont détectés automatiquement.
                    </p>
                    <div class="mt-4">
                        <PrimaryButton :class="{ 'opacity-25': enCours }" :disabled="enCours" @click="analyser">Analyser le fichier</PrimaryButton>
                    </div>
                </div>

                <template v-if="etape === 'apercu' && analyse">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            Correspondance des colonnes — {{ analyse.total_lignes }} ligne(s) détectée(s)
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div v-for="champ in analyse.champs_disponibles" :key="champ">
                                <InputLabel :value="LIBELLES_CHAMPS[champ] ?? champ" />
                                <SelectInput v-model="mapping[champ]" class="mt-1 block w-full">
                                    <option :value="null">— Non importé —</option>
                                    <option v-for="entete in analyse.en_tetes" :key="entete" :value="entete">{{ entete }}</option>
                                </SelectInput>
                            </div>
                        </div>

                        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 space-y-1">
                            <p>
                                <span class="font-medium text-gray-700 dark:text-gray-300">Colonnes téléphone détectées :</span>
                                {{ analyse.colonnes_telephones_detectees.length > 0 ? analyse.colonnes_telephones_detectees.join(', ') : 'aucune' }}
                            </p>
                            <p v-if="analyse.colonne_whatsapp_detectee">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Colonne WhatsApp détectée :</span>
                                {{ analyse.colonne_whatsapp_detectee }}
                            </p>
                            <p>
                                <span class="font-medium text-gray-700 dark:text-gray-300">Colonnes e-mail détectées :</span>
                                {{ analyse.colonnes_emails_detectees.length > 0 ? analyse.colonnes_emails_detectees.join(', ') : 'aucune' }}
                            </p>
                        </div>

                        <div class="mt-4">
                            <InputLabel value="En cas de doublon (même numéro déjà connu)" />
                            <div class="mt-1 flex gap-4 text-sm">
                                <label class="flex items-center gap-2">
                                    <input v-model="strategieDoublon" type="radio" value="mettre_a_jour" />
                                    Mettre à jour le contact existant
                                </label>
                                <label class="flex items-center gap-2">
                                    <input v-model="strategieDoublon" type="radio" value="ignorer" />
                                    Ignorer le doublon
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ignorer</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nom</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Téléphone</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">WhatsApp</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Numéro normalisé</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <tr v-for="ligne in analyse.apercu" :key="ligne.index" :class="lignesIgnorees.has(ligne.index) ? 'opacity-40' : ''">
                                    <td class="px-4 py-2"><input type="checkbox" :checked="lignesIgnorees.has(ligne.index)" @change="basculerIgnoree(ligne.index)" /></td>
                                    <td class="px-4 py-2">{{ ligne.nom ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        {{ ligne.telephone ?? '—' }}
                                        <span v-if="ligne.numeros_secondaires > 0" class="text-xs text-gray-400">(+{{ ligne.numeros_secondaires }} autre{{ ligne.numeros_secondaires > 1 ? 's' : '' }})</span>
                                    </td>
                                    <td class="px-4 py-2">{{ ligne.whatsapp ?? '—' }}</td>
                                    <td class="px-4 py-2">{{ ligne.numero_normalise ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" :class="ligne.valide ? 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300' : 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300'">
                                            {{ ligne.valide ? 'Valide' : 'À corriger' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-if="analyse.total_lignes > analyse.apercu.length" class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500">
                            Aperçu limité aux {{ analyse.apercu.length }} premières lignes — les {{ analyse.total_lignes - analyse.apercu.length }} lignes restantes seront importées avec le même mappage.
                        </p>
                    </div>

                    <div class="flex gap-3">
                        <SecondaryButton @click="recommencer">Annuler</SecondaryButton>
                        <PrimaryButton @click="confirmer">Confirmer l'importation</PrimaryButton>
                    </div>
                </template>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                    <div class="p-6 pb-3">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Imports précédents</h3>
                    </div>
                    <div v-if="imports.data.length === 0" class="px-6 pb-6 text-sm text-gray-400 dark:text-gray-500">Aucun import pour le moment.</div>
                    <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                        <Link v-for="imp in imports.data" :key="imp.id" :href="route('admin.contacts.import.show', imp.id)" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ imp.nom_fichier }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    par {{ imp.admin?.name ?? '—' }} le {{ formatDateHeure(imp.created_at) }} — {{ statutLabels[imp.statut] }}
                                </div>
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ imp.lignes_importees }} créés · {{ imp.lignes_maj }} maj</div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
