// Catégories proposées à l'inscription (création de la première boutique). La
// colonne boutiques.categorie reste un texte libre : "Autre" permet de saisir une
// catégorie absente de la liste. Même liste dans l'application mobile :
// flutter_app/lib/utils/categories_boutique.dart -- les garder identiques.
export const CATEGORIE_AUTRE = 'Autre';

export const categoriesBoutique = [
    { nom: 'Mode & vêtements', icone: '👗' },
    { nom: 'Chaussures & sacs', icone: '👟' },
    { nom: 'Beauté & cosmétiques', icone: '💄' },
    { nom: 'Bijoux & montres', icone: '💍' },
    { nom: 'Alimentation & boissons', icone: '🛒' },
    { nom: 'Restauration', icone: '🍽️' },
    { nom: 'Téléphonie & électronique', icone: '📱' },
    { nom: 'Informatique', icone: '💻' },
    { nom: 'Électroménager', icone: '🔌' },
    { nom: 'Maison & décoration', icone: '🛋️' },
    { nom: 'Quincaillerie & BTP', icone: '🔨' },
    { nom: 'Santé & pharmacie', icone: '💊' },
    { nom: 'Bébé & enfants', icone: '🧸' },
    { nom: 'Sport & loisirs', icone: '⚽' },
    { nom: 'Auto & moto', icone: '🚗' },
    { nom: 'Agriculture & élevage', icone: '🌾' },
    { nom: 'Librairie & fournitures', icone: '📚' },
    { nom: 'Services', icone: '🛠️' },
    { nom: 'Événementiel', icone: '🎉' },
    { nom: 'Formation & éducation', icone: '🎓' },
];
