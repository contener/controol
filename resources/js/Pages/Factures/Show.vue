<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InvoicePreview from '@/Components/InvoicePreview.vue';
import FacturesSubNav from './Partials/FacturesSubNav.vue';

const props = defineProps({
    facture: Object,
    apercu: Object,
});

const statutLabels = {
    brouillon: 'Brouillon',
    envoyee: 'Envoyée',
    payee: 'Payée',
    annulee: 'Annulée',
};

const statutClasses = {
    brouillon: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
    envoyee: 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-300',
    payee: 'bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300',
    annulee: 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300',
};

const changerStatut = (event) => {
    const statut = event.target.value;
    if (!statut || statut === props.facture.statut) {
        return;
    }
    router.patch(route('factures.statut', props.facture.id), { statut });
};

const supprimer = () => {
    if (confirm(`Supprimer la facture ${props.facture.numero} ?`)) {
        router.delete(route('factures.destroy', props.facture.id));
    }
};

const dupliquer = () => {
    router.post(route('factures.dupliquer', props.facture.id));
};

const imprimer = () => {
    window.print();
};
</script>

<template>
    <AppLayout :title="`Facture ${facture.numero}`">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">Facture {{ facture.numero }}</h2>
                    <span class="px-2 py-1 text-xs font-medium rounded-full" :class="statutClasses[facture.statut]">{{ statutLabels[facture.statut] }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <SelectInput :model-value="facture.statut" @update:modelValue="(v) => changerStatut({ target: { value: v } })" class="text-sm">
                        <option value="brouillon">Brouillon</option>
                        <option value="envoyee">Envoyée</option>
                        <option value="payee">Payée</option>
                        <option value="annulee">Annulée</option>
                    </SelectInput>
                    <SecondaryButton @click="imprimer">Imprimer</SecondaryButton>
                    <a :href="route('factures.pdf', facture.id)" target="_blank">
                        <SecondaryButton>Télécharger PDF</SecondaryButton>
                    </a>
                    <SecondaryButton @click="dupliquer">Dupliquer</SecondaryButton>
                    <Link v-if="facture.statut === 'brouillon'" :href="route('factures.edit', facture.id)">
                        <PrimaryButton>Modifier</PrimaryButton>
                    </Link>
                    <DangerButton v-if="facture.statut === 'brouillon'" @click="supprimer">Supprimer</DangerButton>
                </div>
            </div>
        </template>

        <div class="py-8 print:py-0">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6 print:max-w-none print:px-0">
                <div class="print:hidden">
                    <FacturesSubNav />
                </div>

                <div class="print-area bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg print:shadow-none print:rounded-none">
                    <InvoicePreview :modele-id="facture.modele_id" :apercu="apercu" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
