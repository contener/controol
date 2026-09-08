<script setup>
const props = defineProps({
    groupesPermissions: {
        type: Object,
        required: true,
    },
    modelValue: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const estCochee = (cle) => props.modelValue.includes(cle);

const basculer = (cle) => {
    const valeurs = estCochee(cle)
        ? props.modelValue.filter((v) => v !== cle)
        : [...props.modelValue, cle];
    emit('update:modelValue', valeurs);
};
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div v-for="(groupe, cleGroupe) in groupesPermissions" :key="cleGroupe">
            <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase mb-2">{{ groupe.label }}</h4>
            <div class="space-y-1.5">
                <label
                    v-for="(libelle, cle) in groupe.permissions"
                    :key="cle"
                    class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"
                >
                    <input
                        type="checkbox"
                        class="rounded border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-blue-600 shadow-sm focus:ring-blue-500"
                        :checked="estCochee(cle)"
                        @change="basculer(cle)"
                    >
                    {{ libelle }}
                </label>
            </div>
        </div>
    </div>
</template>
