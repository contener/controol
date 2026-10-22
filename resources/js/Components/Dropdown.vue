<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: Array,
        default: () => ['py-1', 'bg-white', 'dark:bg-slate-800', 'dark:border', 'dark:border-slate-700'],
    },
    // 'bottom' (par défaut, nav du haut) ou 'top' -- nécessaire pour un déclencheur
    // ancré en bas de l'écran (ex. bouton flottant), dont le panneau doit s'ouvrir
    // vers le haut plutôt que de sortir de l'écran.
    position: {
        type: String,
        default: 'bottom',
    },
});

let open = ref(false);

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => document.removeEventListener('keydown', closeOnEscape));

const widthClass = computed(() => {
    return {
        '48': 'w-48',
    }[props.width.toString()];
});

const positionClasses = computed(() => (props.position === 'top' ? 'bottom-full mb-2' : 'mt-2'));

// Classes Tailwind écrites en toutes lettres (jamais interpolées dans un template
// string) -- le scanner JIT de Tailwind lit le code source tel quel, une classe
// construite dynamiquement (`origin-${x}-left`) ne serait jamais détectée et son CSS
// ne serait jamais généré.
const alignmentClasses = computed(() => {
    if (props.position === 'top') {
        if (props.align === 'left') {
            return 'ltr:origin-bottom-left rtl:origin-bottom-right start-0';
        }

        if (props.align === 'right') {
            return 'ltr:origin-bottom-right rtl:origin-bottom-left end-0';
        }

        return 'origin-bottom';
    }

    if (props.align === 'left') {
        return 'ltr:origin-top-left rtl:origin-top-right start-0';
    }

    if (props.align === 'right') {
        return 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    return 'origin-top';
});
</script>

<template>
    <div class="relative">
        <div @click="open = ! open">
            <slot name="trigger" />
        </div>

        <!-- Full Screen Dropdown Overlay -->
        <div v-show="open" class="fixed inset-0 z-40" @click="open = false" />

        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="absolute z-50 rounded-md shadow-lg"
                :class="[widthClass, positionClasses, alignmentClasses]"
                style="display: none;"
                @click="open = false"
            >
                <div class="rounded-md ring-1 ring-black ring-opacity-5" :class="contentClasses">
                    <slot name="content" />
                </div>
            </div>
        </transition>
    </div>
</template>
