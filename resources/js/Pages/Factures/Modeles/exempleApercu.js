// Données factices utilisées uniquement pour les miniatures de la galerie de modèles
// (Factures/Modeles/Index.vue) — aucune donnée réelle n'existe encore à ce stade.
export const exempleApercu = {
    meta: {
        numero: 'FAC-2026-0001',
        statut: 'brouillon',
        date_emission: '01/09/2026',
        date_echeance: '15/09/2026',
        devise: 'XAF',
    },
    boutique: {
        nom: 'Ma Boutique',
        logo_url: null,
        adresse: 'Avenue Kennedy',
        ville: 'Douala',
        pays: 'Cameroun',
        telephone: '+237 6 00 00 00 00',
        whatsapp: '+237 6 00 00 00 00',
        email: 'contact@maboutique.demo',
    },
    client: {
        nom: 'Société Alpha SARL',
        email: 'contact@alpha.cm',
        telephone: '+237 6 11 11 11 11',
        adresse: null,
        ville: 'Douala',
        pays: 'Cameroun',
        numero_fiscal: null,
    },
    lignes: [
        { designation: 'Ordinateur portable 15"', description: null, quantite: 2, prix_unitaire: 350000, tva_taux: 19.25, remise_ligne: 0, montant_ht: 700000, montant_tva: 134750, montant_ttc: 834750 },
        { designation: 'Installation et configuration', description: null, quantite: 1, prix_unitaire: 25000, tva_taux: 19.25, remise_ligne: 0, montant_ht: 25000, montant_tva: 4812.5, montant_ttc: 29812.5 },
    ],
    totaux: { sous_total: 725000, remise: 0, total_tva: 139562.5, total_ttc: 864562.5 },
    notes: 'Merci de votre confiance.',
};
