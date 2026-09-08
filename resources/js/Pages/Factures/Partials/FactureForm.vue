<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
    boutique: {
        type: Object,
        default: null,
    },
    tauxTvaDefaut: {
        type: Number,
        default: 0,
    },
});

// Le NUI est une propriété de la boutique, pas de la facture — le modifier ici met à
// jour la boutique elle-même (via boutiques.nui) et s'appliquera à toutes ses futures
// factures, pas seulement celle en cours de saisie.
const formNui = useForm({ nui: props.boutique?.nui ?? '' });
const nuiEnregistre = ref(false);
watch(() => props.boutique?.nui, (valeur) => { formNui.nui = valeur ?? ''; });

const enregistrerNui = () => {
    formNui.patch(route('boutiques.nui', props.boutique.id), {
        preserveScroll: true,
        onSuccess: () => {
            nuiEnregistre.value = true;
            setTimeout(() => { nuiEnregistre.value = false; }, 2000);
        },
    });
};

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
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Client</h3>
            <InputLabel for="client_id" value="Client *" />
            <SelectInput id="client_id" v-model="form.client_id" class="mt-1 block w-full">
                <option :value="null" disabled>Sélectionner un client</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.nom }}</option>
            </SelectInput>
            <InputError :message="form.errors.client_id" class="mt-2" />
        </section>

        <section v-if="boutique">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Boutique</h3>
            <InputLabel for="nui" value="NUI (Numéro d'Identifiant Unique)" />
            <div class="mt-1 flex items-start gap-2">
                <TextInput id="nui" v-model="formNui.nui" type="text" class="block w-full" placeholder="M012312345678A" />
                <SecondaryButton type="button" :disabled="formNui.processing" @click="enregistrerNui">
                    {{ nuiEnregistre ? '✓ Enregistré' : 'Enregistrer' }}
                </SecondaryButton>
            </div>
            <InputError :message="formNui.errors.nui" class="mt-2" />
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">S'applique à toutes les factures de cette boutique — modifiable aussi depuis les paramètres de la boutique.</p>
        </section>

        <section>
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Informations de la facture</h3>
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
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Produits</h3>
                <SecondaryButton type="button" @click="ajouterLigne">+ Ajouter un produit</SecondaryButton>
            </div>
            <InputError :message="form.errors.lignes" class="mb-2" />

            <div class="space-y-3">
                <div v-for="(ligne, index) in form.lignes" :key="index" class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 space-y-2">
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

                    <div class="text-right text-sm text-slate-500 dark:text-slate-400">
                        Total ligne : <span class="font-medium text-slate-900 dark:text-slate-100">{{ formatMontant(calculerLigne(ligne).montant_ttc) }}</span>
                    </div>
                </div>

                <div v-if="form.lignes.length === 0" class="text-center text-slate-400 dark:text-slate-500 py-6 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    Aucun produit. Cliquez sur "Ajouter un produit".
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6">
            <div>
                <InputLabel for="notes" value="Notes" />
                <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" />
                <InputError :message="form.errors.notes" class="mt-2" />
            </div>

            <div>
                <InputLabel for="garantie" value="Garantie" />
                <textarea id="garantie" v-model="form.garantie" rows="2" class="mt-1 block w-full border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm" placeholder="Ex. : Garantie de 6 mois. Ne couvre pas les dommages liés à l'eau, aux chocs ou à une mauvaise utilisation." />
                <InputError :message="form.errors.garantie" class="mt-2" />
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Propre à cette facture. Affichée en bas de la facture, en petits caractères.</p>
            </div>

            <div>
                <InputLabel for="remise" value="Remise globale (montant)" />
                <TextInput id="remise" v-model="form.remise" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError :message="form.errors.remise" class="mt-2" />
            </div>

            <div class="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Sous-total HT</span><span class="text-slate-900 dark:text-slate-100">{{ formatMontant(sousTotal) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">TVA</span><span class="text-slate-900 dark:text-slate-100">{{ formatMontant(totalTva) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500 dark:text-slate-400">Remise</span><span class="text-slate-900 dark:text-slate-100">- {{ formatMontant(form.remise) }}</span></div>
                <div class="flex justify-between font-semibold text-base border-t border-slate-200 dark:border-slate-700 pt-1 mt-1"><span>Total TTC</span><span>{{ formatMontant(totalTtc) }}</span></div>
            </div>
        </section>
    </div>
</template>
