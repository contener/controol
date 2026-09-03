import { computed } from 'vue';

/**
 * Calcule les montants d'une ligne de facture — identique à la formule appliquée côté
 * serveur dans FactureService::enregistrerLignes(). Utilisée à la fois par l'aperçu
 * temps réel et par le tableau de saisie (FactureForm.vue) pour éviter deux implémentations
 * divergentes du même calcul.
 */
export function calculerLigne(ligne) {
    const quantite = Number(ligne.quantite) || 0;
    const prixUnitaire = Number(ligne.prix_unitaire) || 0;
    const remiseLigne = Number(ligne.remise_ligne) || 0;
    const tvaTaux = Number(ligne.tva_taux) || 0;

    const montant_ht = quantite * prixUnitaire - remiseLigne;
    const montant_tva = montant_ht * (tvaTaux / 100);

    return { montant_ht, montant_tva, montant_ttc: montant_ht + montant_tva };
}

const clientVide = {
    nom: 'Client non sélectionné',
    email: null,
    telephone: null,
    adresse: null,
    ville: null,
    pays: null,
    numero_fiscal: null,
};

/**
 * Construit la forme de données normalisée consommée par les 10 modèles de facture
 * (Vue) à partir de l'état réactif du formulaire — même forme que
 * App\Support\FactureApercuBuilder côté serveur (voir sa docblock), pour qu'un modèle
 * n'ait jamais à connaître deux mappings différents.
 *
 * clients/boutique sont acceptés en fonctions "getter" (ex. () => props.boutique) et non
 * en valeurs destructurées directement : si on capturait juste props.boutique une seule
 * fois à l'appel, un rechargement partiel Inertia qui remplace cet objet (ex. après
 * modification du NUI depuis l'écran de saisie) ne serait jamais reflété dans l'aperçu —
 * le computed() ci-dessous doit lire la prop en direct à chaque recalcul pour rester
 * réactif à un remplacement complet de l'objet, pas seulement à une mutation interne.
 *
 * @param {object} form - l'objet réactif retourné par useForm() d'Inertia
 * @param {{clients: () => Array, boutique: () => object, numeroPrevisualise?: string}} contexte
 */
export function useFactureApercu(form, { clients = () => [], boutique = () => ({}), numeroPrevisualise = '(brouillon)' } = {}) {
    return computed(() => {
        const clientsValeur = clients();
        const boutiqueValeur = boutique();
        const client = clientsValeur.find((c) => c.id === Number(form.client_id)) ?? null;

        const lignes = (form.lignes ?? []).map((ligne) => ({
            designation: ligne.designation || '',
            description: ligne.description || null,
            quantite: Number(ligne.quantite) || 0,
            prix_unitaire: Number(ligne.prix_unitaire) || 0,
            tva_taux: Number(ligne.tva_taux) || 0,
            remise_ligne: Number(ligne.remise_ligne) || 0,
            ...calculerLigne(ligne),
        }));

        const sousTotal = lignes.reduce((acc, l) => acc + l.montant_ht, 0);
        const totalTva = lignes.reduce((acc, l) => acc + l.montant_tva, 0);
        const remise = Number(form.remise) || 0;

        return {
            meta: {
                numero: numeroPrevisualise,
                statut: form.statut ?? 'brouillon',
                date_emission: form.date_emission || null,
                date_echeance: form.date_echeance || null,
                devise: boutiqueValeur?.devise ?? 'XAF',
            },
            boutique: {
                nom: boutiqueValeur?.nom ?? '',
                logo_url: boutiqueValeur?.logo_path ? `/storage/${boutiqueValeur.logo_path}` : null,
                adresse: boutiqueValeur?.adresse ?? null,
                ville: boutiqueValeur?.ville ?? null,
                pays: boutiqueValeur?.pays ?? null,
                telephone: boutiqueValeur?.telephone ?? null,
                whatsapp: boutiqueValeur?.whatsapp ?? null,
                email: boutiqueValeur?.email ?? null,
                nui: boutiqueValeur?.nui ?? null,
                note_pied_facture: boutiqueValeur?.note_pied_facture ?? null,
            },
            client: client ?? clientVide,
            lignes,
            totaux: {
                sous_total: sousTotal,
                remise,
                total_tva: totalTva,
                total_ttc: sousTotal + totalTva - remise,
            },
            notes: form.notes || null,
        };
    });
}
