<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    form: Object,
    processing: Boolean,
    modification: {
        type: Boolean,
        default: false,
    },
    produit: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['submit']);

const estProduit = computed(() => props.form.type === 'produit');

const onPhoto = (event) => {
    props.form.photo = event.target.files[0] ?? null;
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="emit('submit')">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <InputLabel for="type" value="Type *" />
                <SelectInput id="type" v-model="form.type" class="mt-1 block w-full">
                    <option value="produit">Produit</option>
                    <option value="service">Service</option>
                </SelectInput>
                <InputError :message="form.errors.type" class="mt-2" />
            </div>

            <div>
                <InputLabel for="nom" value="Nom *" />
                <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required autofocus />
                <InputError :message="form.errors.nom" class="mt-2" />
            </div>

            <div>
                <InputLabel for="reference" value="Référence / SKU" />
                <TextInput id="reference" v-model="form.reference" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.reference" class="mt-2" />
            </div>

            <div>
                <InputLabel for="unite" value="Unité *" />
                <TextInput id="unite" v-model="form.unite" type="text" class="mt-1 block w-full" placeholder="pièce, heure, kg..." required />
                <InputError :message="form.errors.unite" class="mt-2" />
            </div>

            <div>
                <InputLabel for="prix_achat" value="Prix d'achat" />
                <TextInput id="prix_achat" v-model="form.prix_achat" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError :message="form.errors.prix_achat" class="mt-2" />
            </div>

            <div>
                <InputLabel for="prix_vente" value="Prix de vente *" />
                <TextInput id="prix_vente" v-model="form.prix_vente" type="number" step="0.01" min="0" class="mt-1 block w-full" required />
                <InputError :message="form.errors.prix_vente" class="mt-2" />
            </div>

            <div>
                <InputLabel for="tva_taux" value="Taux de TVA (%)" />
                <TextInput id="tva_taux" v-model="form.tva_taux" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" />
                <InputError :message="form.errors.tva_taux" class="mt-2" />
            </div>

            <div>
                <InputLabel for="categorie" value="Catégorie (vitrine publique)" />
                <TextInput id="categorie" v-model="form.categorie" type="text" class="mt-1 block w-full" />
                <InputError :message="form.errors.categorie" class="mt-2" />
            </div>

            <div>
                <InputLabel for="mini_characteristics" value="Mini caractéristiques" />
                <TextInput
                    id="mini_characteristics"
                    v-model="form.mini_characteristics"
                    type="text"
                    maxlength="250"
                    class="mt-1 block w-full"
                    placeholder="RAM 8 Go • SSD 256 Go • Core i5"
                />
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Ajoutez quelques informations essentielles visibles directement dans votre boutique.
                </p>
                <InputError :message="form.errors.mini_characteristics" class="mt-2" />
            </div>

            <div>
                <InputLabel for="promotion_prix" value="Prix promotionnel (optionnel)" />
                <TextInput id="promotion_prix" v-model="form.promotion_prix" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError :message="form.errors.promotion_prix" class="mt-2" />
            </div>
        </div>

        <div>
            <InputLabel for="description" value="Description" />
            <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
            <InputError :message="form.errors.description" class="mt-2" />
        </div>

        <div>
            <InputLabel for="photo" value="Photo (vitrine publique)" />
            <img v-if="produit?.photo_path" :src="`/storage/${produit.photo_path}`" class="h-20 w-20 object-cover rounded mb-2" alt="Photo actuelle">
            <input id="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm dark:text-slate-100" @change="onPhoto">
            <InputError :message="form.errors.photo" class="mt-2" />
        </div>

        <label class="flex items-center gap-2">
            <Checkbox v-model:checked="form.actif" />
            <span class="text-sm text-slate-700 dark:text-slate-300">Visible sur la boutique publique</span>
        </label>

        <div v-if="estProduit" class="border-t border-slate-200 dark:border-slate-700 pt-6 space-y-4">
            <label class="flex items-center gap-2">
                <Checkbox v-model:checked="form.gere_stock" />
                <span class="text-sm text-slate-700 dark:text-slate-300">Gérer le stock pour ce produit</span>
            </label>

            <div v-if="form.gere_stock" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div v-if="!modification">
                    <InputLabel for="quantite_stock" value="Quantité initiale en stock" />
                    <TextInput id="quantite_stock" v-model="form.quantite_stock" type="number" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.quantite_stock" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="seuil_alerte" value="Seuil d'alerte de stock" />
                    <TextInput id="seuil_alerte" v-model="form.seuil_alerte" type="number" min="0" class="mt-1 block w-full" />
                    <InputError :message="form.errors.seuil_alerte" class="mt-2" />
                </div>
            </div>
            <p v-if="modification" class="text-sm text-slate-500 dark:text-slate-400">
                La quantité en stock se modifie depuis la page Stock (mouvements d'entrée/sortie/ajustement).
            </p>
        </div>

        <div class="flex items-center justify-end gap-4">
            <PrimaryButton :disabled="processing">Enregistrer</PrimaryButton>
        </div>
    </form>
</template>
