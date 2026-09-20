<script setup>
import { ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    url: {
        type: String,
        required: true,
    },
    texte: {
        type: String,
        default: '',
    },
    // Phrase affichée au-dessus du code QR sur l'affiche téléchargeable (jamais dans
    // les boutons de partage WhatsApp/Facebook/Telegram, qui utilisent `texte`).
    phraseQrCode: {
        type: String,
        default: 'Scannez ce code avec l\'appareil photo de votre téléphone',
    },
});

const copie = ref(false);

const copierLien = async () => {
    try {
        await navigator.clipboard.writeText(props.url);
        copie.value = true;
        setTimeout(() => { copie.value = false; }, 2000);
    } catch (e) {
        // Contexte non sécurisé ou permission refusée : on laisse l'utilisateur copier
        // le lien à la main, déjà affiché à l'écran.
    }
};

const lienWhatsapp = () => `https://wa.me/?text=${encodeURIComponent(`${props.texte} ${props.url}`)}`;
const lienFacebook = () => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.url)}`;
const lienTelegram = () => `https://t.me/share/url?url=${encodeURIComponent(props.url)}&text=${encodeURIComponent(props.texte)}`;

const peutPartageNatif = typeof navigator !== 'undefined' && !!navigator.share;
const partagerNatif = () => {
    navigator.share({ title: props.texte, url: props.url }).catch(() => {});
};

// Généré entièrement côté navigateur, à la demande seulement (jamais au chargement
// de la page) -- aucun aller-retour serveur, le lien ne quitte jamais le poste du
// visiteur avant d'être encodé.
const qrCodeUrl = ref(null);
const genereEnCours = ref(false);
const telechargementEnCours = ref(false);
const erreurQrCode = ref(false);

const afficherQrCode = async () => {
    if (qrCodeUrl.value || genereEnCours.value) {
        qrCodeUrl.value = null;
        return;
    }

    genereEnCours.value = true;
    erreurQrCode.value = false;
    try {
        qrCodeUrl.value = await QRCode.toDataURL(props.url, { width: 400, margin: 2 });
    } catch (e) {
        erreurQrCode.value = true;
    } finally {
        genereEnCours.value = false;
    }
};

const chargerImage = (src) => new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = reject;
    img.src = src;
});

const decouperTexte = (ctx, texte, largeurMax) => {
    const mots = texte.split(' ');
    const lignes = [];
    let ligneActuelle = '';

    for (const mot of mots) {
        const essai = ligneActuelle ? `${ligneActuelle} ${mot}` : mot;
        if (ctx.measureText(essai).width > largeurMax && ligneActuelle) {
            lignes.push(ligneActuelle);
            ligneActuelle = mot;
        } else {
            ligneActuelle = essai;
        }
    }
    if (ligneActuelle) lignes.push(ligneActuelle);

    return lignes;
};

/**
 * Affiche imprimable au format A4 (210×297mm, ~150 DPI) : phrase accrocheuse en
 * haut, code QR en haute résolution centré, marge de sécurité constante autour de
 * tout le contenu pour ne jamais rien faire déborder à l'impression.
 */
const construireAfficheA4 = async () => {
    const largeurPage = 1240;
    const hauteurPage = 1754;
    const marge = 110;
    const largeurZoneTexte = largeurPage - marge * 2 - 60;
    const hauteurLigneTexte = 68;
    const tailleQr = 760;
    const espaceApresTexte = 70;
    const espaceApresQr = 50;
    const hauteurPied = 40;

    const canvas = document.createElement('canvas');
    canvas.width = largeurPage;
    canvas.height = hauteurPage;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, largeurPage, hauteurPage);

    // Cadre matérialisant la marge de sécurité d'impression -- jamais de contenu
    // dessiné hors de ce cadre.
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    ctx.strokeRect(marge, marge, largeurPage - marge * 2, hauteurPage - marge * 2);

    ctx.font = 'bold 54px "Segoe UI", Arial, sans-serif';
    const lignesTexte = decouperTexte(ctx, props.phraseQrCode, largeurZoneTexte);

    // Bloc entier (texte + QR + pied de page) centré verticalement dans la page,
    // quelle que soit la longueur de la phrase.
    const hauteurBlocTexte = lignesTexte.length * hauteurLigneTexte;
    const hauteurContenu = hauteurBlocTexte + espaceApresTexte + tailleQr + espaceApresQr + hauteurPied;
    let y = marge + Math.max(0, (hauteurPage - marge * 2 - hauteurContenu) / 2);

    ctx.fillStyle = '#0f172a';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    for (const ligne of lignesTexte) {
        ctx.fillText(ligne, largeurPage / 2, y);
        y += hauteurLigneTexte;
    }

    y += espaceApresTexte;
    const qrHauteResolution = await QRCode.toDataURL(props.url, { width: 900, margin: 1 });
    const qrImage = await chargerImage(qrHauteResolution);
    ctx.drawImage(qrImage, (largeurPage - tailleQr) / 2, y, tailleQr, tailleQr);

    y += tailleQr + espaceApresQr;
    ctx.font = '32px "Segoe UI", Arial, sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText('controol.fr', largeurPage / 2, y);

    return canvas.toDataURL('image/png');
};

const telechargerAffiche = async () => {
    telechargementEnCours.value = true;
    erreurQrCode.value = false;
    try {
        const affiche = await construireAfficheA4();
        const lien = document.createElement('a');
        lien.href = affiche;
        lien.download = 'controool-qr-code.png';
        lien.click();
    } catch (e) {
        erreurQrCode.value = true;
    } finally {
        telechargementEnCours.value = false;
    }
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            @click="copierLien"
        >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
            </svg>
            {{ copie ? 'Lien copié !' : 'Copier le lien' }}
        </button>

        <a :href="lienWhatsapp()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-green-600 text-white shadow-sm hover:bg-green-700 hover:shadow-md transition-all duration-150">
            WhatsApp
        </a>
        <a :href="lienFacebook()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-blue-600 text-white shadow-sm hover:bg-blue-700 hover:shadow-md transition-all duration-150">
            Facebook
        </a>
        <a :href="lienTelegram()" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md bg-sky-500 text-white shadow-sm hover:bg-sky-600 hover:shadow-md transition-all duration-150">
            Telegram
        </a>
        <button
            v-if="peutPartageNatif"
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            @click="partagerNatif"
        >
            Autres...
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors duration-150"
            :disabled="genereEnCours"
            @click="afficherQrCode"
        >
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h6v6h-6v-6zm10.5 0h6v6h-6v-6zm-10.5 10.5h6v6h-6v-6zm10.5 3h2.25m0-3v6m0-6h-2.25m0 3h-3" />
            </svg>
            {{ qrCodeUrl ? 'Masquer le code QR' : (genereEnCours ? 'Génération...' : 'Code QR') }}
        </button>
    </div>

    <div v-if="erreurQrCode" class="mt-3 text-sm text-red-600 dark:text-red-400">
        Impossible de générer le code QR pour le moment. Réessayez.
    </div>

    <div v-if="qrCodeUrl" class="mt-3 inline-flex flex-col items-center gap-2 p-4 bg-white rounded-lg border border-slate-200 dark:border-slate-600">
        <img :src="qrCodeUrl" alt="Code QR du lien" class="size-40 sm:size-48">
        <button
            type="button"
            :disabled="telechargementEnCours"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-150 disabled:opacity-60"
            @click="telechargerAffiche"
        >
            {{ telechargementEnCours ? 'Préparation de l\'affiche...' : 'Télécharger (affiche A4)' }}
        </button>
    </div>
</template>
