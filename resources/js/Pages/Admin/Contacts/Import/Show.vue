<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminSubNav from '../../Partials/AdminSubNav.vue';

const props = defineProps({
    resultat: Object,
});

const formatDateHeure = (d) => new Date(d).toLocaleString('fr-FR');
const statutLabels = { en_cours: 'En cours', termine: 'Terminé', echoue: 'Échoué' };
</script>

<template>
    <AppLayout title="Résultat de l'import">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">{{ resultat.nom_fichier }}</h2>
                <Link :href="route('admin.contacts.import.index')" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">← Retour aux imports</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <AdminSubNav />

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Importé par {{ resultat.admin?.name ?? '—' }} le {{ formatDateHeure(resultat.created_at) }} — {{ statutLabels[resultat.statut] }}
                    </p>

                    <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total lignes</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ resultat.total_lignes }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Créés</div>
                            <div class="mt-1 text-xl font-semibold text-green-600 dark:text-green-400">{{ resultat.lignes_importees }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Mis à jour</div>
                            <div class="mt-1 text-xl font-semibold text-indigo-600 dark:text-indigo-400">{{ resultat.lignes_maj }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Ignorés / erreurs</div>
                            <div class="mt-1 text-xl font-semibold text-gray-500 dark:text-gray-400">{{ resultat.lignes_ignorees }} / {{ resultat.lignes_erreur }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="resultat.erreurs && resultat.erreurs.length > 0" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Erreurs ({{ resultat.erreurs.length }})</h3>
                    <ul class="space-y-1 text-sm text-red-700 dark:text-red-400">
                        <li v-for="(erreur, index) in resultat.erreurs" :key="index">Ligne {{ erreur.ligne }} : {{ erreur.message }}</li>
                    </ul>
                </div>

                <Link :href="route('admin.contacts.index', { source: `Import : ${resultat.nom_fichier}` })" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                    Voir les contacts de cet import →
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
