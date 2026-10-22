<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

// Bouton flottant déplaçable à la souris (ordinateur) ou au doigt (téléphone/tablette) --
// cliquer-glisser déplace le bouton, un simple clic/tap (sans déplacement notable)
// déclenche toujours son action normale (navigation, ouverture de menu...). Téléporté
// directement dans <body> pour ne jamais dépendre du contexte de positionnement d'un
// ancêtre de la page (transform, overflow...) -- c'est ce qui causait le bouton
// "Voir ma boutique" à sembler défiler avec la page sur téléphone au lieu de rester fixe.
// Position persistée par bouton (clé de stockage dédiée) pour que chaque bouton se
// déplace indépendamment et retrouve sa place après un rechargement.
const props = defineProps({
    storageKey: { type: String, required: true },
    defaultBottom: { type: Number, default: 24 },
    defaultRight: { type: Number, default: 24 },
});

const cle = `fab_position_${props.storageKey}`;
const elementRef = ref(null);
const position = ref(null);
const dragging = ref(false);
let aDeplace = false;
let depart = null;

const MARGE = 8;
const SEUIL_DEPLACEMENT = 6;

function tailleBouton() {
    return {
        largeur: elementRef.value?.offsetWidth ?? 56,
        hauteur: elementRef.value?.offsetHeight ?? 56,
    };
}

function contraindre(x, y) {
    const { largeur, hauteur } = tailleBouton();
    const maxX = Math.max(MARGE, window.innerWidth - largeur - MARGE);
    const maxY = Math.max(MARGE, window.innerHeight - hauteur - MARGE);

    return {
        x: Math.min(Math.max(MARGE, x), maxX),
        y: Math.min(Math.max(MARGE, y), maxY),
    };
}

function chargerPositionSauvegardee() {
    try {
        const brut = localStorage.getItem(cle);
        if (!brut) {
            return null;
        }
        const { x, y } = JSON.parse(brut);
        if (typeof x !== 'number' || typeof y !== 'number') {
            return null;
        }
        return contraindre(x, y);
    } catch {
        return null;
    }
}

function sauvegarderPosition(x, y) {
    try {
        localStorage.setItem(cle, JSON.stringify({ x, y }));
    } catch {
        // Stockage indisponible (navigation privée, quota...) -- le bouton reste
        // simplement à sa position par défaut au prochain chargement, sans bloquer.
    }
}

function positionParDefaut() {
    const { largeur, hauteur } = tailleBouton();

    return contraindre(
        window.innerWidth - props.defaultRight - largeur,
        window.innerHeight - props.defaultBottom - hauteur,
    );
}

function onResize() {
    if (position.value) {
        position.value = contraindre(position.value.x, position.value.y);
    }
}

function onPointerDown(e) {
    if (e.button !== undefined && e.button !== 0) {
        return;
    }

    depart = { x: e.clientX, y: e.clientY, posX: position.value.x, posY: position.value.y };
    aDeplace = false;
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp);
}

function onPointerMove(e) {
    if (!depart) {
        return;
    }

    const dx = e.clientX - depart.x;
    const dy = e.clientY - depart.y;

    if (!dragging.value && Math.hypot(dx, dy) > SEUIL_DEPLACEMENT) {
        dragging.value = true;
        aDeplace = true;
    }

    if (dragging.value) {
        position.value = contraindre(depart.posX + dx, depart.posY + dy);
    }
}

function onPointerUp() {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);

    if (dragging.value && position.value) {
        sauvegarderPosition(position.value.x, position.value.y);
    }

    dragging.value = false;
    depart = null;
}

// Capturée (pas en bulles) pour intercepter le clic avant qu'il n'atteigne le lien/
// bouton réel à l'intérieur -- un déplacement réel ne doit jamais aussi déclencher la
// navigation/l'ouverture du menu qu'un simple tap aurait déclenchée.
function onClickCapture(e) {
    if (aDeplace) {
        e.preventDefault();
        e.stopPropagation();
        aDeplace = false;
    }
}

onMounted(() => {
    position.value = chargerPositionSauvegardee() ?? positionParDefaut();
    window.addEventListener('resize', onResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="position"
            ref="elementRef"
            class="fixed z-50 select-none"
            :style="{ left: position.x + 'px', top: position.y + 'px', cursor: dragging ? 'grabbing' : 'grab', touchAction: 'none' }"
            @pointerdown="onPointerDown"
            @click.capture="onClickCapture"
        >
            <slot :dragging="dragging" />
        </div>
    </Teleport>
</template>
