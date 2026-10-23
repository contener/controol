<script setup>
import { useCurrencyFormat } from '@/Composables/useCurrencyFormat';

const props = defineProps({
    apercu: Object,
    align: {
        type: String,
        default: 'right', // 'right' ou 'center'
    },
});

const { formatMontant } = useCurrencyFormat();
</script>

<template>
    <div
        v-if="apercu.meta.type === 'recu' && apercu.totaux.montant_avance !== null"
        class="mt-3 w-72 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm space-y-1.5"
        :class="align === 'center' ? 'mx-auto' : 'ml-auto'"
    >
        <div class="flex justify-between text-slate-600"><span>Montant total à verser</span><span>{{ formatMontant(apercu.totaux.total_ttc, apercu.meta.devise) }}</span></div>
        <div class="flex justify-between text-slate-600"><span>Avance versée</span><span>{{ formatMontant(apercu.totaux.montant_avance, apercu.meta.devise) }}</span></div>
        <div class="flex justify-between items-center font-bold text-emerald-900 pt-1.5 border-t border-emerald-200">
            <span>Reste à payer</span><span>{{ formatMontant(apercu.totaux.reste_a_payer, apercu.meta.devise) }}</span>
        </div>
        <div v-if="apercu.meta.date_echeance" class="text-xs text-emerald-700 pt-0.5">Reste à régler avant le {{ apercu.meta.date_echeance }}</div>
    </div>
</template>
