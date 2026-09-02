<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import PartageLiens from '@/Components/PartageLiens.vue';

const props = defineProps({
    boutiques: Object,
    categories: Array,
    villes: Array,
    promotions: Array,
    filtres: Object,
    marketplaceUrl: String,
});

const recherche = ref(props.filtres.recherche ?? '');
const categorie = ref(props.filtres.categorie ?? '');
const ville = ref(props.filtres.ville ?? '');

let timeoutId = null;
watch(recherche, () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(appliquerFiltres, 300);
});
watch([categorie, ville], appliquerFiltres);

function appliquerFiltres() {
    router.get(route('marketplace.index'), {
        recherche: recherche.value,
        categorie: categorie.value,
        ville: ville.value,
    }, { preserveState: true, replace: true });
}

const formatMontant = (montant, devise) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(montant) + ' ' + devise;
</script>

<template>
    <div class="space-y-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Marketplace</h1>
            <p class="mt-2 text-gray-500">Découvrez des boutiques et leurs produits, partout où elles sont installées.</p>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-4 flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">🔎</span>
                <TextInput v-model="recherche" placeholder="Rechercher une boutique ou un produit..." class="w-full pl-9" />
            </div>
            <SelectInput v-model="categorie" class="sm:w-56">
                <option value="">Toutes les catégories</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </SelectInput>
            <SelectInput v-model="ville" class="sm:w-56">
                <option value="">Toutes les villes</option>
                <option v-for="v in villes" :key="v" :value="v">{{ v }}</option>
            </SelectInput>
        </div>

        <div v-if="promotions.length > 0">
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Promotions en ce moment</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <Link
                    v-for="produit in promotions"
                    :key="produit.id"
                    :href="route('public.boutique', produit.boutique.slug)"
                    class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition"
                >
                    <div class="aspect-square bg-gray-100">
                        <img v-if="produit.photo_path" :src="`/storage/${produit.photo_path}`" class="w-full h-full object-cover" :alt="produit.nom" loading="lazy">
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300 text-2xl">📦</div>
                    </div>
                    <div class="p-2">
                        <div class="text-xs text-gray-500 truncate">{{ produit.boutique.nom }}</div>
                        <div class="text-sm font-medium text-gray-900 truncate">{{ produit.nom }}</div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-sm font-semibold text-red-600">{{ formatMontant(produit.promotion_prix, produit.boutique.devise) }}</span>
                            <span class="text-xs text-gray-400 line-through">{{ formatMontant(produit.prix_vente, produit.boutique.devise) }}</span>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-3">Boutiques</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-if="boutiques.data.length === 0" class="col-span-full text-center text-gray-400 py-16 bg-white rounded-lg shadow-sm">
                    Aucune boutique ne correspond à votre recherche pour le moment.
                </div>

                <Link
                    v-for="boutique in boutiques.data"
                    :key="boutique.id"
                    :href="route('public.boutique', boutique.slug)"
                    class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-md transition flex flex-col"
                >
                    <div class="h-32 bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
                        <img v-if="boutique.logo_path" :src="`/storage/${boutique.logo_path}`" class="h-20 w-20 rounded-full object-cover border-4 border-white shadow" :alt="boutique.nom" loading="lazy">
                        <div v-else class="h-20 w-20 rounded-full bg-white flex items-center justify-center text-2xl font-bold text-indigo-600 shadow">
                            {{ boutique.nom.charAt(0) }}
                        </div>
                    </div>
                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-semibold text-gray-900">{{ boutique.nom }}</h3>
                        <p class="text-sm text-gray-500">{{ [boutique.categorie, boutique.ville].filter(Boolean).join(' · ') || '—' }}</p>
                        <p class="mt-2 text-xs text-gray-400">{{ boutique.produits_count }} produit(s)</p>
                        <span class="mt-3 inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md bg-indigo-50 text-indigo-700">
                            Voir la boutique
                        </span>
                    </div>
                </Link>
            </div>

            <div v-if="boutiques.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
                <Link
                    v-for="(link, index) in boutiques.links"
                    :key="index"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    class="px-3 py-1 text-sm rounded border"
                    :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                    :disabled="!link.url"
                />
            </div>
        </div>

        <div class="bg-white shadow-sm rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Partager la Marketplace</h3>
            <PartageLiens :url="marketplaceUrl" texte="Découvrez cette marketplace de boutiques locales !" />
        </div>
    </div>
</template>
