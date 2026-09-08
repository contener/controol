<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';

const props = defineProps({
    clients: Object,
    filtres: Object,
});

const recherche = ref(props.filtres.recherche ?? '');
const etiquette = ref(props.filtres.etiquette ?? '');

const appliquerFiltres = () => {
    router.get(route('clients.index'), { recherche: recherche.value, etiquette: etiquette.value }, {
        preserveState: true,
        replace: true,
    });
};

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch(etiquette, appliquerFiltres);

const supprimer = (client) => {
    if (confirm(`Supprimer le client "${client.nom}" ?`)) {
        router.delete(route('clients.destroy', client.id));
    }
};

const etiquetteClasses = {
    prospect: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    client: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
};
</script>

<template>
    <AppLayout title="Clients">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">Clients</h2>
                <Link :href="route('clients.create')">
                    <PrimaryButton>Nouveau client</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg p-4 flex flex-col sm:flex-row gap-4">
                    <TextInput v-model="recherche" placeholder="Rechercher un client (nom, email, téléphone)..." class="flex-1" />
                    <SelectInput v-model="etiquette" class="sm:w-48">
                        <option value="">Toutes les étiquettes</option>
                        <option value="prospect">Prospect</option>
                        <option value="client">Client</option>
                    </SelectInput>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-sm sm:rounded-lg overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Étiquette</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Factures</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-if="clients.data.length === 0">
                                <td colspan="5" class="px-6 py-6 text-center text-slate-400 dark:text-slate-500">Aucun client trouvé.</td>
                            </tr>
                            <tr v-for="client in clients.data" :key="client.id" class="hover:bg-slate-50 dark:hover:bg-slate-700">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-slate-100">{{ client.nom }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    <div>{{ client.email }}</div>
                                    <div>{{ client.telephone }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="etiquetteClasses[client.etiquette]">
                                        {{ client.etiquette === 'client' ? 'Client' : 'Prospect' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ client.factures_count }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-3">
                                    <Link :href="route('clients.edit', client.id)" class="text-blue-600 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">Modifier</Link>
                                    <button class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300" @click="supprimer(client)">Supprimer</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="clients.links.length > 3" class="flex flex-wrap gap-1">
                    <Link
                        v-for="(link, index) in clients.links"
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
    </AppLayout>
</template>
