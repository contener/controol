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
            <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">{{ groupe.label }}</h4>
            <div class="space-y-1.5">
                <label
                    v-for="(libelle, cle) in groupe.permissions"
                    :key="cle"
                    class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                >
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        :checked="estCochee(cle)"
                        @change="basculer(cle)"
                    >
                    {{ libelle }}
                </label>
            </div>
        </div>
    </div>
</template>
