import Modele01Standard from './Modele01Standard.vue';
import Modele02Classique from './Modele02Classique.vue';
import Modele03Business from './Modele03Business.vue';
import Modele04Modern from './Modele04Modern.vue';
import Modele05Premium from './Modele05Premium.vue';
import Modele06Corporate from './Modele06Corporate.vue';
import Modele07Ecommerce from './Modele07Ecommerce.vue';
import Modele08Elegant from './Modele08Elegant.vue';
import Modele09Pro from './Modele09Pro.vue';
import Modele10Executive from './Modele10Executive.vue';

// Un seul point de vérité pour "quel composant Vue affiche ce modele_id" — utilisé par
// InvoicePreview.vue (aperçu temps réel + plein écran). Les libellés/permissions
// viennent toujours du serveur (prop `modeles`), jamais recalculés ici.
export const registreModeles = {
    1: Modele01Standard,
    2: Modele02Classique,
    3: Modele03Business,
    4: Modele04Modern,
    5: Modele05Premium,
    6: Modele06Corporate,
    7: Modele07Ecommerce,
    8: Modele08Elegant,
    9: Modele09Pro,
    10: Modele10Executive,
};
