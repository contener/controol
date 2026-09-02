<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { calculerLigne } from '@/Composables/useFactureApercu';
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    form: Object,
    clients: Array,
    produits: Array,
    tauxTvaDefaut: {
        type: Number,
        default: 0,
    },
});

const { formatMontant } = useCurrencyFormat();

const ligneVide = () => ({
    produit_id: null,
    designation: '',
    description: '',
    quantite: 1,
    prix_unitaire: 0,
    tva_taux: props.tauxTvaDefaut,
    remise_ligne: 0,
});

const ajouterLigne = () => {
    props.form.lignes.push(ligneVide());
};

const supprimerLigne = (index) => {
    props.form.lignes.splice(index, 1);
};

const appliquerProduit = (ligne) => {
    if (!ligne.produit_id) {
        return;
    }
    const produit = props.produits.find((p) => p.id === Number(ligne.produit_id));
    if (produit) {
        ligne.designation = produit.nom;
        ligne.prix_unitaire = produit.prix_vente;
        ligne.tva_taux = produit.tva_taux ?? props.tauxTvaDefaut;
    }
};

const sousTotal = computed(() => props.form.lignes.reduce((acc, l) => acc + calculerLigne(l).montant_ht, 0));
const totalTva = computed(() => props.form.lignes.reduce((acc, l) => acc + calculerLigne(l).montant_tva, 0));
const totalTtc = computed(() => sousTotal.value + totalTva.value - (Number(props.form.remise) || 0));
</script>

<template>
    <div class="space-y-8">
        <section>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Client</h3>
            <InputLabel for="client_id" value="Client *" />
            <SelectInput id="client_id" v-model="form.client_id" class="mt-1 block w-full">
                <option :value="null" disabled>Sélectionner un client</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.nom }}</option>
            </SelectInput>
            <InputError :message="form.errors.client_id" class="mt-2" />
        </section>

        <section>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Informations de la facture</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <InputLabel for="date_emission" value="Date d'émission *" />
                    <TextInput id="date_emission" v-model="form.date_emission" type="date" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.date_emission" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="date_echeance" value="Date d'échéance" />
                    <TextInput id="date_echeance" v-model="form.date_echeance" type="date" class="mt-1 block w-full" />
                    <InputError :message="form.errors.date_echeance" class="mt-2" />
                </div>
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Produits</h3>
                <SecondaryButton type="button" @click="ajouterLigne">+ Ajouter un produit</SecondaryButton>
            </div>
            <InputError :message="form.errors.lignes" class="mb-2" />

            <div class="space-y-3">
                <div v-for="(ligne, index) in form.lignes" :key="index" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
                    <div class="flex items-start gap-2">
                        <SelectInput v-model="ligne.produit_id" class="block w-full text-sm" @change="appliquerProduit(ligne)">
                            <option :value="null">Ligne libre</option>
                            <option v-for="produit in produits" :key="produit.id" :value="produit.id">{{ produit.nom }}</option>
                        </SelectInput>
                        <button type="button" class="text-red-500 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 shrink-0 px-1" @click="supprimerLigne(index)">✕</button>
                    </div>

                    <div>
                        <TextInput v-model="ligne.designation" type="text" placeholder="Désignation" class="block w-full text-sm" required />
                        <InputError :message="form.errors[`lignes.${index}.designation`]" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div>
                            <InputLabel class="text-xs" value="Qté" />
                            <TextInput v-model="ligne.quantite" type="number" step="0.01" min="0.01" class="block w-full text-sm" required />
                        </div>
                        <div>
                            <InputLabel class="text-xs" value="Prix unitaire" />
                            <TextInput v-model="ligne.prix_unitaire" type="number" step="0.01" min="0" class="block w-full text-sm" required />
                        </div>
                        <div>
                            <InputLabel class="text-xs" value="TVA %" />
                            <TextInput v-model="ligne.tva_taux" type="number" step="0.01" min="0" max="100" class="block w-full text-sm" />
                        </div>
                        <div>
                            <InputLabel class="text-xs" value="Remise" />
                            <TextInput v-model="ligne.remise_ligne" type="number" step="0.01" min="0" class="block w-full text-sm" />
                        </div>
                    </div>

                    <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                        Total ligne : <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatMontant(calculerLigne(ligne).montant_ttc) }}</span>
                    </div>
                </div>

                <div v-if="form.lignes.length === 0" class="text-center text-gray-400 dark:text-gray-500 py-6 border border-dashed border-gray-200 dark:border-gray-700 rounded-lg">
                    Aucun produit. Cliquez sur "Ajouter un produit".
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6">
            <div>
                <InputLabel for="notes" value="Notes" />
                <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.notes" class="mt-2" />
            </div>

            <div>
                <InputLabel for="remise" value="Remise globale (montant)" />
                <TextInput id="remise" v-model="form.remise" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError :message="form.errors.remise" class="mt-2" />
            </div>

            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Sous-total HT</span><span class="text-gray-900 dark:text-gray-100">{{ formatMontant(sousTotal) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">TVA</span><span class="text-gray-900 dark:text-gray-100">{{ formatMontant(totalTva) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-gray-400">Remise</span><span class="text-gray-900 dark:text-gray-100">- {{ formatMontant(form.remise) }}</span></div>
                <div class="flex justify-between font-semibold text-base border-t border-gray-200 dark:border-gray-700 pt-1 mt-1"><span>Total TTC</span><span>{{ formatMontant(totalTtc) }}</span></div>
            </div>
        </section>
    </div>
</template>
