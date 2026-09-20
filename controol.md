# CONTROOL.md — Prompt de reconstruction intégrale

> **À l'attention de Claude** : ce fichier est un filet de sécurité. S'il n'existe
> plus que ce fichier (ordinateur perdu, dépôt Git perdu, hébergement perdu), une
> nouvelle session Claude Code doit pouvoir reconstruire une application
> fonctionnellement équivalente rien qu'en le lisant.
>
> **Règle de maintenance** : après toute fonctionnalité nouvelle, tout changement de
> comportement, ou toute décision de conception non triviale, mets ce fichier à jour
> dans la même session — pas "plus tard". Ajoute une entrée à la fin de la section
> « Historique des évolutions majeures », et modifie la section concernée ci-dessus
> si le comportement décrit a changé. Un utilisateur ne demandera jamais explicitement
> "mets à jour controol.md" pour chaque petite modification — c'est un réflexe
> attendu de ta part, pas une tâche à part. Si CLAUDE.md existe dans ce projet, il
> pointe vers cette règle ; s'il n'existe pas, considère quand même cette instruction
> comme active en permanence.
>
> **Ce que ce fichier n'est PAS** : un dump exhaustif du code. C'est une spécification
> fonctionnelle et technique — assez précise pour reconstruire le comportement, les
> règles métier et les choix d'architecture, sans reproduire chaque ligne. Le code
> réel (s'il existe encore) reste la source de vérité en cas de divergence.

---

## 1. Vue d'ensemble

**Controool** est un SaaS français de gestion de boutique/commerce, déployé sur
**https://controol.fr**. Un utilisateur peut gérer une ou plusieurs boutiques
(produits/services, clients, factures, dépenses, stock), avoir une page publique par
boutique, apparaître dans une marketplace de découverte, se faire suivre par des
abonnés, et parrainer d'autres utilisateurs contre commission. La plateforme a son
propre espace Super Administrateur avec permissions granulaires.

Toute l'application — code, commentaires, noms de variables/méthodes/routes, messages
utilisateur — est en **français**. Ce n'est pas une traduction d'une base anglaise :
c'est la langue native du code (`Facture`, `Boutique`, `Client`, `estActive()`,
`nombreAbonnes()`, etc.). Garder cette convention est essentiel pour que le résultat
"ressemble" au projet d'origine.

---

## 2. Stack technique

- **Backend** : Laravel 12 (PHP ^8.2), architecture `bootstrap/app.php` (pas de
  `Kernel.php` classique — middleware/exceptions configurés via closures).
- **Frontend** : Inertia.js v2 + Vue 3 (Composition API, exclusivement `<script
  setup>`), Tailwind CSS (palette bleu/slate, dark mode via stratégie `class`).
- **Auth** : Laravel Jetstream (stack `inertia`, guard `sanctum`) + Fortify. Features
  Jetstream actives : photos de profil, suppression de compte. **Teams NON activé**
  (le scaffolding Jetstream Teams existe dans le code mais `HasTeams` n'est jamais
  utilisé — ne pas construire de fonctionnalité dessus). Features Fortify actives :
  inscription, reset password, mise à jour profil/mot de passe, **2FA avec
  confirmation obligatoire**, **passkeys (WebAuthn)**. Vérification d'email
  **désactivée**.
- **Base de données** : MySQL en production. `SESSION_DRIVER=database`,
  `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `MAIL_MAILER=log` (**aucun
  email réel n'est envoyé aujourd'hui** — tout part dans les logs, pas de SMTP
  configuré ; à corriger si des emails transactionnels deviennent nécessaires).
- **PDF** : `barryvdh/laravel-dompdf` (remote images désactivées par défaut — les
  logos sont embarqués en base64 data URI, jamais en URL distante).
- **Export** : `maatwebsite/excel`.
- **Routes JS** : `tightenco/ziggy` (`route()` global côté Vue).
- **i18n** : `vue-i18n`, `fr` (langue par défaut) + `en`, dictionnaires dans
  `resources/js/lang/{fr,en}.json`, clé `nav.*` pour la navigation.
- **Build** : Vite. Déploiement d'assets par bascule atomique de dossier (voir §8).
- **Service annexe indépendant** : `whatsapp-connector/` — micro-service Node.js
  (`@whiskeysockets/baileys` + `express` + `qrcode`), **jamais mélangé au code
  Laravel/Vue**, communique par HTTP avec un secret partagé
  (`services.whatsapp_connector.url`/`secret`). **Non déployé actuellement** (l'URL
  est vide en prod) — le code Laravel gère cette absence gracieusement (placeholder
  d'interface, jamais d'exception qui casse la page).

---

## 3. Conventions et principes établis (à respecter impérativement)

Ces règles ne sont pas des détails — elles définissent l'identité technique du
projet. Un rebuild qui les ignore produira un résultat qui "marche" mais qui ne sera
plus le même projet.

1. **Statut calculé, jamais persisté en double.** Si un état peut se déduire de
   colonnes déjà existantes (timestamps `converti_a`/`annule_a`, `desabonne_a`,
   `annule_at`...), ne JAMAIS ajouter une colonne `statut` séparée qui pourrait se
   désynchroniser. Exemples : `EssaiUtilisateur::statut()`,
   `CommissionParrainage::estActive()` (`statut !== 'annulee'`), `Suivi::estActif()`
   (`desabonne_a === null`).

2. **Modèle "grand livre" pour tout ce qui est financier.** Ne jamais marquer une
   ligne de commission/gain comme "payée" individuellement quand un règlement
   partiel est possible. Le montant déjà réglé se calcule en sommant une table de
   règlements séparée (`reglements_parrainage`), jamais en modifiant l'historique.
   Voir §7 Parrainage.

3. **Champs sensibles jamais mass-assignables.** `role`, `parrain_id`,
   `code_parrainage`, `marketplace_disabled_by_admin`, `admin_role_label`, `est_actif`
   sont **absents de `$fillable`** sur leurs modèles respectifs — modifiables
   uniquement via `forceFill()` côté serveur, jamais via un tableau venant d'une
   requête. C'est la protection anti-élévation de privilèges de référence du projet.

4. **Isolation multi-boutique via `BelongsToBoutique`** (`app/Models/Concerns/
   BelongsToBoutique.php`) : trait appliqué à `Produit`, `Client`, `Facture`,
   `Depense`, `MouvementStock`, `CompteurFacture`, `Conversation`,
   `DestinationSociale`, `CampagneSociale`, `WhatsAppAgent`. Ajoute un scope global
   qui filtre sur `Auth::user()->currentBoutique->id` — **fail-closed** : si
   l'utilisateur n'a pas de boutique courante, le scope force `boutique_id = 0`
   (aucun résultat) plutôt que de sauter le filtre. Auto-remplit `boutique_id` à la
   création. Les services qui ont besoin d'un accès inter-boutique ou hors contexte
   authentifié utilisent explicitement `withoutGlobalScopes()`.

5. **Chaque paiement doit valider une opération réelle avant tout effet financier
   dérivé.** Une commission de parrainage, une conversion d'essai, une activation
   d'abonnement — rien de tout cela ne se déclenche ailleurs que depuis
   `PaiementValidationService::approuver()`, appelé uniquement par un admin habilité,
   jamais par une déclaration du frontend.

6. **"Marketplace" ≠ "boutique publique".** Le lien direct `/boutique/{slug}` d'une
   boutique reste accessible et montre son catalogue, indépendamment de son
   éligibilité à la découverte globale `/marketplace` (qui dépend du plan payant +
   activation explicite du propriétaire). Mais depuis l'évolution "visibilité par
   produit", `marketplace_visible` sur `Produit` gouverne désormais TOUTE visibilité
   publique du produit (boutique directe ET marketplace) — voir §7 Produits.

7. **Catalogue de permissions admin à deux niveaux.** `App\Support\AdminPermissions`
   sépare `GROUPES` (catalogue déclaré complet, y compris des clés pas encore
   branchées à un vrai contrôle) de `CLES_ACTIVES` (sous-ensemble réellement vérifié
   par le middleware `admin.permission:<cle>`). Permet de préparer un groupe de
   permissions dans l'UI avant que la fonctionnalité correspondante existe
   réellement, sans donner un faux sentiment de sécurité.

8. **Réutiliser avant de recréer.** Avant toute nouvelle fonctionnalité, vérifier si
   une infrastructure existante peut être étendue : `PartageLiens.vue` (partage
   copier/WhatsApp/Facebook/Telegram/natif) sert à la fois au partage de boutique et
   au lien de parrainage ; `NotificationUtilisateur` sert aux rappels d'essai, aux
   nouveaux produits suivis, et aux commissions de parrainage ; `AdminAudit` trace
   toutes les actions admin sensibles (paiements, marketplace, parrainage,
   administrateurs) sans table dédiée par fonctionnalité.

9. **Journalisation "best-effort" pour tout ce qui est non critique.** Les inserts
   d'événements (visites, notifications) sont enveloppés dans un `try/catch` qui
   avale l'exception — ils ne doivent jamais faire échouer le rendu d'une page ou une
   action principale.

10. **Tests** : `RefreshDatabase`, trait `Tests\CreatesBoutique`
    (`creerUtilisateurAvecBoutique()`), helper `creerAdminAvecPermissions(array
    $permissions)` répété dans chaque fichier de test admin (crée un `User`
    `role=admin` + des lignes `AdminPermission`). `UserFactory` considère la 2FA
    "déjà confirmée" par défaut (`two_factor_confirmed_at => now()`) pour ne pas
    casser tous les tests qui ne testent pas spécifiquement ce point — les tests qui
    vérifient le blocage 2FA le remettent explicitement à `null`.

11. **Style de commentaire** : jamais de commentaire qui décrit CE QUE fait le code
    (déjà lisible par le nommage) — uniquement le POURQUOI quand ce n'est pas
    évident (contrainte cachée, invariant, contournement d'un bug précis).

---

## 4. Modèle de données (vue d'ensemble par domaine)

### Comptes & auth
- **`users`** : `name, email, password, two_factor_secret/recovery_codes/confirmed_at
  (Fortify), current_team_id (inutilisé), profile_photo_path, date_naissance, ville,
  telephone, whatsapp, role (user|admin|super_admin, défaut user, hors $fillable),
  current_boutique_id (FK boutiques), locale (fr), theme (light), admin_role_label,
  est_actif (bool défaut true), code_parrainage (unique, nullable, généré à la
  demande), parrain_id (FK users auto-référencée, nullOnDelete)`.
- **`admin_permissions`** : `user_id, permission` (string libre, unique par paire).
- **`admin_audits`** : `admin_id, action, resource, resource_id, ancienne_valeur/
  nouvelle_valeur (json), ip_address` — pas d'`updated_at`, jamais modifié.

### Boutiques & catalogue
- **`boutiques`** : `user_id, nom, slug (unique), logo_path, banniere_path,
  description, categorie, adresse, ville, pays, telephone, whatsapp, email, devise
  (XAF), taux_tva_defaut (19.25), nui, note_pied_facture, statut (active|suspendue),
  facebook_url/instagram_url/telegram_url, marketplace_visible (bool),
  marketplace_disabled_by_admin (bool, hors $fillable)`.
- **`produits`** : `boutique_id, type (produit|service), nom, description, reference
  (unique par boutique), prix_achat, prix_vente, unite (défaut "pièce"), tva_taux,
  gere_stock (bool), quantite_stock, seuil_alerte, photo_path, actif (bool défaut
  true), marketplace_visible (bool défaut FALSE — opt-in explicite), promotion_prix,
  categorie, mini_characteristics (250 car. max)`.
- **`clients`** : `boutique_id, nom, email, telephone, adresse, ville, pays,
  numero_fiscal, notes, etiquette (prospect|client, défaut prospect)`.

### Facturation
- **`factures`** : `boutique_id, client_id, type (facture|proforma, immuable après
  création), numero (unique par boutique, format PREFIX-ANNEE-0000), statut
  (brouillon|envoyee|payee|annulee), modele_id (1-10), date_emission, date_echeance,
  sous_total/remise/total_tva/total_ttc, notes, garantie (texte libre par facture,
  distinct du pied de page fixe de la boutique), created_by`.
- **`facture_lignes`** : `facture_id, produit_id (nullable — lignes libres), designation,
  description, quantite, prix_unitaire, tva_taux, remise_ligne, montant_ht/tva/ttc,
  ordre`.
- **`compteurs_facture`** : `boutique_id, annee, type, dernier_numero` — unique par
  `(boutique_id, annee, type)`, séquence séparée Facture/Proforma. Génération
  concurrente-safe (`lockForUpdate()` + transaction).
- **`depenses`** : `boutique_id, categorie (texte libre, liste suggérée côté UI
  seulement), montant, description, date_depense, created_by`.
- **`mouvements_stock`** : `boutique_id, produit_id, type (entree|sortie|ajustement),
  quantite, quantite_avant, quantite_apres, motif, user_id, facture_id` — seul point
  de mutation du stock, `StockService::enregistrerMouvement()`, verrou de ligne
  (`lockForUpdate`).

### Abonnements & paiements
- **`plans`** : `code, nom, prix, lien_paiement, devise, duree_jours,
  limite_boutiques/produits/clients/factures/stocks/destinations_sociales,
  marketplace (bool), publication_sociale (bool), modeles_facture_avances (bool),
  chatbot_whatsapp (bool), ordre`. Seedés : **gratuit** (0 XAF, tout limité, aucune
  feature payante), **basique** (5000 XAF, limites généreuses, marketplace +
  modèles avancés), **pro** (15000 XAF, illimité, toutes features).
- **`abonnements`** : `user_id, plan_id, statut (actif|expire|annule|en_attente),
  date_debut, date_fin (null = illimité)`. Source de vérité unique :
  `scopeActuellementActif()`.
- **`paiements`** : `user_id, abonnement_id, montant, devise, moyen_paiement, statut
  (en_attente|approuve|rejete), reference_transaction, motif_rejet, valide_par,
  valide_at`.
- **`paiement_audits`** : trace chaque action de validation/rejet.
- **`essais_utilisateurs`** : essai gratuit 7 jours du plan Basique à l'inscription
  (`prix_promo` à 3500 XAF), `date_debut/date_fin, converti_a, annule_a` — statut
  toujours calculé, jamais stocké.
- **`modeles_notification_essai`** / **`parametres_essai`** : templates + réglages
  des rappels quotidiens d'essai.

### Parrainage & commissions
- **`commissions_parrainage`** : `parrain_id, filleul_id, paiement_id (UNIQUE — une
  commission par paiement, garanti en base), montant_eligible, taux, montant_commission,
  statut (disponible|annulee — PAS de statut "payée" ici), annule_par, annule_at,
  motif_annulation`.
- **`reglements_parrainage`** : `parrain_id, montant, methode_paiement,
  reference_transaction, notes, traite_par` — pas d'`updated_at`, immuable.
- Taux fixe **5 %**, constante `ParrainageService::TAUX_COMMISSION`, récurrent sur
  CHAQUE paiement validé du filleul (pas seulement le premier).

### Communauté / partage
- **`suivis_boutique`** : `boutique_id, user_id, notifications_actives (bool),
  abonne_a, desabonne_a` — unique par `(user_id, boutique_id)`, "actif" =
  `desabonne_a === null`.
- **`evenements_invitation_boutique`** : `boutique_id, user_id (nullable),
  visiteur_token (nullable, via VisiteurIdentiteService), type_evenement` — table
  d'événements immuable (lien_visite, compte_cree, boutique_creee, abonnement_cree).
- **`notifications_utilisateurs`** : `user_id, type, titre, message, lien (nullable,
  URL absolue déjà construite côté serveur), est_promotionnelle, lu_a` — pas
  d'`updated_at`. Types connus : `essai_rappel`, `nouveau_produit`,
  `commission_parrainage`.

### Messagerie boutique publique
- **`conversations`** : lien acheteur/visiteur ↔ boutique (optionnellement lié à un
  produit), `visiteur_user_id` OU `visiteur_token` (identité anonyme via cookie
  signé, `VisiteurIdentiteService`), `statut` (ouverte|bloquee|archivee|fermee),
  compteurs de messages non lus des deux côtés.
- **`conversation_messages`** : `expediteur (boutique|visiteur), contenu`.

### Réseaux sociaux (partage manuel, jamais automatisé)
- **`destinations_sociales`** : `boutique_id, nom, lien, type (libre), statut
  (en_attente|en_cours|envoye|echec|non_autorise), ordre`.
- **`campagnes_sociales`** : `boutique_id, message, statut
  (brouillon|en_cours|terminee|arretee), intervalle_secondes, created_by,
  started_at/ended_at`.
- **`campagne_destinations`** : join `campagne_id ↔ destination_sociale_id`, même
  enum statut, `traite_at`. **Principe explicite : aucune publication automatique** —
  le système génère un message copier-coller et attend une confirmation humaine
  explicite par destination avant de marquer "envoyé".

### WhatsApp
- **`whatsapp_agents`** : `boutique_id, nom (défaut "Assistant"), actif (bool défaut
  false), langue (défaut fr), personnalite, ton, message_accueil,
  message_hors_horaires, whatsapp_statut (défaut deconnecte), whatsapp_numero,
  whatsapp_connecte_a`. **Persona/config prêts, mais AUCUNE intégration LLM réelle
  branchée aujourd'hui** — pas d'appel OpenAI/Anthropic dans le code. À construire si
  la fonctionnalité doit vraiment répondre automatiquement.
- **`whatsapp_contact_logs`** : historique des relances WhatsApp manuelles
  (admin → utilisateur ou admin → contact CRM), lien wa.me généré côté serveur
  uniquement (jamais le numéro brut exposé à un admin sans la permission
  `whatsapp.voir`).

### CRM prospection
- **`contacts`** : liste de prospects distincte des vrais comptes `users` —
  `nom/prenom/nom_famille, telephone, whatsapp, telephones_secondaires (json),
  numero_normalise, statut_whatsapp (inconnu|sur_whatsapp|pas_sur_whatsapp|
  numero_invalide), statut_commercial (progression ordonnée : nouveau → a_contacter →
  contacte → a_repondu → interesse → pas_interesse → compte_cree → converti →
  ne_plus_contacter → archive), email, ville, entreprise, poste, source, notes,
  utilisateur_id (lien vers un vrai compte), import_id, created_by`.
- Rapprochement automatique via `App\Observers\UserObserver` : à la création/mise à
  jour d'un `User`, cherche un `Contact` non lié par numéro normalisé et le lie,
  faisant progresser son `statut_commercial` vers `compte_cree` **seulement si** son
  statut actuel est moins avancé dans la séquence (ne régresse jamais un `converti`).
- **`contact_imports`** : suivi des imports en masse (upload → mapping colonnes →
  confirmation).

---

## 5. Fonctionnalités — spécification par module

### 5.1 Authentification & compte
Inscription/connexion Jetstream+Fortify standard. 2FA avec code de récupération et
confirmation obligatoire avant activation. Passkeys (WebAuthn) disponibles.
Vérification d'email désactivée. Remember-me cookie : **30 jours** (le défaut Laravel
de ~400 jours a été jugé trop long et réduit intentionnellement, cf. §7 Sécurité).
Profil : infos perso, mot de passe, 2FA, sessions actives, suppression de compte, +
sections métier ajoutées (Abonnement, Parrainage).

### 5.2 Boutiques
Un utilisateur peut posséder plusieurs boutiques (limite selon plan). Une "boutique
courante" (`current_boutique_id`) détermine le contexte de toutes les pages
boutique-scopées. Chaque boutique a un slug unique, une page publique
`/boutique/{slug}` (accessible sans connexion), un logo/bannière, des réseaux sociaux,
un NUI (identifiant fiscal camerounais), un pied de page de facture personnalisable.

### 5.3 Produits & Services
CRUD avec type produit/service. Le suivi de stock (`gere_stock`) ne s'active que pour
un `type=produit`, jamais un service ; l'activer consomme la limite de plan
`limite_stocks`. `marketplace_visible` (défaut **false**) contrôle toute visibilité
publique — un produit fraîchement créé n'apparaît **nulle part** publiquement (ni
`/boutique/{slug}`, ni `/marketplace`) tant que le propriétaire ne l'active pas
explicitement depuis la liste Produits & Services (colonne dédiée avec interrupteur).
Activer la visibilité déclenche, si le produit est actif, une notification en masse
(insertion unique, pas de boucle) à tous les abonnés actifs de la boutique.

### 5.4 Clients
CRUD simple avec étiquette `prospect`/`client` stockée, plus un **segment calculé**
(jamais persisté) à l'affichage : `prospect` (étiquette), sinon `regulier` (≥3
factures envoyées/payées), sinon `actif` (dernière facture payée/envoyée < 90 jours),
sinon `nouveau`.

### 5.5 Facturation (module le plus complexe)
- Numérotation `PREFIX-ANNEE-0000` par boutique, séquence séparée par année ET par
  type (`FAC-2026-0001`, `PRO-2026-0001`), génération verrouillée (jamais de doublon
  concurrent).
- **Facture vs Proforma** : champ `type`, immuable après création. Une Proforma ne
  touche jamais le stock (indicative uniquement) ; une Facture consomme le stock à la
  création et le restitue à la modification/suppression/annulation.
- Lignes libres (`produit_id` nullable) ou liées à un produit. Calcul ligne : `HT =
  (quantité × prix unitaire) − remise ligne`, `TVA = HT × taux/100`, `TTC = HT + TVA`.
  Totaux facture : `sous_total = Σ HT`, `total_tva = Σ TVA`, `total_ttc = sous_total +
  total_tva − remise_facture` (remise facture appliquée uniquement au total, jamais
  distribuée aux lignes).
- Statuts : `brouillon` (seul état modifiable/supprimable), `envoyee`, `payee`,
  `annulee` (restitue le stock si géré).
- **10 modèles PDF** (`modele_id` 1-10, noms Standard/Classique/Business/Modern/
  Premium/Corporate/Ecommerce/Elegant/Pro/Executive) : les modèles 1-2 sont gratuits,
  3-10 nécessitent `plan.modeles_facture_avances`. L'autorisation est toujours
  vérifiée en direct contre le plan actif, jamais figée sur une facture existante — un
  modèle déjà utilisé reste affichable même si le plan change, seul le CHANGEMENT vers
  un nouveau modèle restreint est bloqué. PDF généré via dompdf depuis une vue Blade
  dédiée par modèle ; même structure de données (`FactureApercuBuilder`) alimente à
  la fois le PDF serveur et l'aperçu live côté Vue (composants Vue miroirs des vues
  Blade, jamais divergents).
- Duplication d'une facture → nouveau brouillon, dates réinitialisées, modèle
  substitué silencieusement par le modèle standard si le plan ne l'autorise plus.

### 5.6 Dépenses
CRUD simple, catégories suggérées en dur côté UI (Loyer, Salaires, Achat marchandise,
Transport, Facture eau/électricité/internet, Marketing, Autre) mais champ texte libre
non contraint en base.

### 5.7 Stock
Point de mutation unique (`StockService::enregistrerMouvement`), jamais de mise à
jour directe de `Produit.quantite_stock` ailleurs. Trois types de mouvement :
entrée/sortie/ajustement (l'ajustement fixe une valeur absolue, pas un delta). Chaque
mouvement s'accompagne d'un motif et, s'il vient d'une facture, du lien vers celle-ci.
Alerte de rupture : `gere_stock && seuil_alerte non nul && quantite_stock <=
seuil_alerte`.

### 5.8 Marketplace (découverte globale)
Page publique listant les boutiques éligibles (`Boutique::eligiblesMarketplace()` —
active + `marketplace_visible` + non désactivée par un admin + plan actuel autorisant
`marketplace`) et une section promotions (produits avec `promotion_prix`, filtrés par
`marketplace_visible` aussi). Recherche par nom de boutique OU produit
marketplace-visible. Toujours accessible à un visiteur non connecté.

### 5.9 Partage de boutique, abonnés, popup d'invitation
- Chaque boutique a un lien de partage = son URL publique directe (pas de système de
  lien court séparé). Composant `PartageLiens.vue` générique (copier / WhatsApp /
  Facebook / Telegram / partage natif du téléphone).
- Un visiteur peut suivre une boutique (`suivis_boutique`, un seul enregistrement par
  paire user/boutique, ré-abonnement = réutilisation de la même ligne). Compteur
  d'abonnés public. Le propriétaire d'une boutique ne peut jamais suivre sa propre
  boutique.
- Popup d'invitation (`PopupInvitationBoutique.vue`) : apparaît 5s après l'arrivée sur
  une page boutique publique, seulement si le visiteur n'est pas connecté OU est
  connecté sans boutique — jamais à quelqu'un qui possède déjà une boutique. Se
  referme et réapparaît toutes les 5 minutes (`sessionStorage`, pas de nouvelle
  requête serveur). **Tout bouton menant à la création de compte/boutique depuis cette
  page** (popup, bandeau bas de page, bouton "+Suivre") transporte la source via
  session (`?boutique=slug` capturé par middleware sur `/register` et
  `/boutiques/creer`) et crédite systématiquement un abonné à la boutique d'origine
  une fois le compte (et/ou la boutique) créé — peu importe le bouton exact cliqué.
- Nouveau produit publié (`marketplace_visible` passe à true) → notification en masse
  aux abonnés actifs avec `notifications_actives=true`, message avec nom/prix/mini
  caractéristiques, lien direct vers la boutique.

### 5.10 Notifications utilisateur (cloche in-app)
Table unique `notifications_utilisateurs`, cloche dans `AppLayout.vue`, compteur non
lues partagé via Inertia (`HandleInertiaRequests::share()`). Chaque notification peut
porter un `lien` (URL absolue déjà construite) — si présent, cliquer dessus redirige
en plus de marquer comme lu.

### 5.11 Abonnements, paiements, essai gratuit
- Paiement manuel : l'utilisateur choisit un plan, un `Paiement` `en_attente` est
  créé, un admin habilité (`paiements.valider`) l'approuve ou le rejette.
  L'approbation (dans une transaction unique) : active le nouvel abonnement, expire
  les autres abonnements actifs de l'utilisateur, marque tout essai en cours comme
  converti, crée la commission de parrainage éligible, journalise. Rien de tout ça ne
  peut se produire hors de ce point d'entrée.
- Essai gratuit : à l'inscription, si le plan `basique` existe, crée un `Abonnement`
  actif de 7 jours + un `EssaiUtilisateur` (prix promo 3500 XAF pendant l'essai vs prix
  normal après) ; sinon repli sur le plan `gratuit`. Rappels quotidiens configurables
  par admin (heure, fuseau, 7 templates de message par jour d'essai). Expiration gérée
  par une commande planifiée qui repasse l'utilisateur au plan Gratuit sans jamais
  supprimer ses données.

### 5.12 Parrainage & commissions
Chaque utilisateur génère un code de parrainage à la demande (différent de son id
interne), lien `/register?ref=CODE`. Attribution du parrain **une seule fois**, à
l'inscription (jamais modifiable ensuite), auto-parrainage bloqué. **5 % récurrent**
sur chaque paiement validé du filleul (renouvellements inclus), calculé et crédité
automatiquement au moment de l'approbation du paiement — jamais avant, jamais via le
frontend. Page utilisateur : lien à partager, statistiques (comptes créés, paiements
commencés/validés, gains générés/disponibles/payés), liste des filleuls (nom + statut
+ commission générée, **jamais** email/téléphone), historique des commissions. Espace
admin dédié (`parrainage.voir`/`gerer`) : vue globale, détail par parrain, règlement
total ou partiel avec confirmation (jamais un simple reset de compteur — crée une
ligne de règlement et recalcule), annulation motivée d'une commission (fraude/erreur),
rien n'est jamais supprimé.

### 5.13 Messagerie boutique publique
Un visiteur (connecté ou anonyme via cookie signé) peut contacter une boutique depuis
un produit ou en général. Conversations avec statuts (ouverte/bloquée/archivée/
fermée), compteurs de non-lus des deux côtés, lien signé temporaire pour qu'un
visiteur anonyme retrouve sa conversation depuis un autre appareil.

### 5.14 Campagnes sociales
Aide à poster un même message sur plusieurs réseaux/groupes **manuellement** — génère
le texte, guide l'utilisateur destination par destination, n'enregistre "envoyé"
qu'après confirmation humaine explicite. Jamais d'automatisation réelle de
publication. Fonctionnalité réservée au plan avec `publication_sociale=true`.

### 5.15 WhatsApp — deux systèmes distincts
- **Agent IA WhatsApp** (`whatsapp_agents`) : configuration de persona (nom, langue,
  personnalité, ton, messages d'accueil/hors horaires) + statut de connexion.
  Connexion réelle via QR gérée par le micro-service Node séparé
  (`whatsapp-connector/`, Baileys — non-officiel, jamais l'API officielle
  WhatsApp Business). **La génération réelle de réponses IA n'est pas implémentée** —
  seule la configuration/connexion existe ; à construire si le comportement de
  réponse automatique doit être réel (nécessiterait de brancher un vrai LLM).
- **Relance WhatsApp manuelle** (admin → utilisateur ou contact CRM) : génère un lien
  `wa.me` avec message pré-rempli, ouvert dans un nouvel onglet, confirmation manuelle
  de l'envoi (jamais d'envoi automatique/API officielle). Numéro jamais exposé côté
  client sans la permission `whatsapp.voir` — le lien final est construit côté
  serveur uniquement.

### 5.16 CRM prospection (Contacts)
Liste de prospects distincte des vrais comptes, import en masse (upload → analyse/
mapping de colonnes → confirmation), export Excel/CSV, statut WhatsApp et statut
commercial (progression ordonnée, ne régresse jamais automatiquement), relance
WhatsApp intégrée. Rapprochement automatique avec un vrai compte `User` par numéro de
téléphone normalisé dès sa création/modification.

### 5.17 Espace Super Administrateur
`/admin`, protégé par rôle (`admin`/`super_admin` actif) **et désormais 2FA confirmée
obligatoire** (voir §7). Sections : dashboard (agrégats globaux), gestion des
administrateurs eux-mêmes (super_admin exclusivement, jamais délégable), utilisateurs
finaux (suspendre/supprimer/relancer), paiements (valider/refuser), marketplace
(modération), notifications d'essai (templates/réglages), parrainage (vue globale,
règlements, annulations), contacts CRM (liste/import/export/relance). Permissions
granulaires par section (`App\Support\AdminPermissions`), super_admin outrepasse
toujours tout. Chaque action admin sensible journalisée dans `admin_audits`.

---

## 6. Design & UI

- Palette Tailwind bleu/slate, dark mode via classe, composants réutilisables dans
  `resources/js/Components/` (boutons Primary/Secondary/Danger, `Badge`, `EmptyState`,
  `PageHeader`, `ConfirmationModal`, `FlashMessages`, inputs stylés). Toujours
  réutiliser un composant existant avant d'en écrire un nouveau qui fait la même
  chose.
- Layout principal `AppLayout.vue` : nav desktop (dropdown compte) + nav mobile
  (menu responsive), sélecteur de boutique courante, cloche de notifications,
  sélecteur langue/thème.
- Toute page listant des données paginées suit le même schéma : recherche debounced
  300ms, filtres en `SelectInput`, tableau avec ligne "aucun résultat", pagination
  Laravel standard rendue en liens.
- Toute action de confirmation destructive ou financière passe par
  `ConfirmationModal.vue` avec un texte explicite du montant/de la cible, jamais un
  simple `confirm()` JS pour une action financière.

---

## 7. Sécurité (règles à ne jamais retirer)

1. **`.env` et le code source jamais dans le webroot public** — séparation stricte
   entre le dossier servi publiquement (uniquement `public/` de Laravel) et le reste
   de l'application. Vérifier systématiquement après tout changement d'hébergement.
2. **CSRF actif sur toutes les routes web**, aucune exemption.
3. **Connexion limitée** : 5 tentatives/minute par couple email+IP (Fortify
   `RateLimiter`).
4. **2FA confirmée obligatoire pour tout `/admin/*`**, y compris super_admin — un mot
   de passe seul ne doit jamais suffire à accéder à l'espace d'administration.
   Vérifier `two_factor_confirmed_at` (jamais `two_factor_secret` seul, qui peut être
   une configuration abandonnée en cours de route). Rediriger vers le profil pour
   activer plutôt qu'un simple 403.
5. **Cookie "Se souvenir de moi" = 30 jours** (`AppServiceProvider::boot()`,
   `Auth::guard('web')->setRememberDuration(60*24*30)`) — le défaut Laravel (~400
   jours) est jugé trop long pour une appli métier avec données financières.
6. **En-têtes de sécurité** (middleware `SecurityHeaders`, global) : `X-Frame-
   Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy:
   strict-origin-when-cross-origin`, `Permissions-Policy` restrictive,
   `Strict-Transport-Security` (uniquement sur requête déjà sécurisée, pour ne
   jamais casser un accès HTTP legitime si jamais HTTPS n'était pas garanti).
7. **Jamais de requête SQL brute** avec entrée utilisateur — Eloquent partout.
8. **Uploads validés comme vraies images** (`'image'` + limite de taille), jamais
   seulement l'extension du nom de fichier.
9. **Aucun secret en dur dans le code** — tout via `.env`/`config/services.php`.
10. **Chaque contrôleur/policy vérifie l'appartenance réelle** (`$user->id ===
    $ressource->boutique->user_id` ou équivalent) — jamais une confiance dans un id
    envoyé par le client sans revérification serveur.
11. **Auditer régulièrement** : `npm audit`/`composer audit` sans vulnérabilité
    connue tolérée en attente, aucun endpoint de debug (`_debugbar`/`telescope`/
    `horizon`) exposé en production.

---

## 8. Déploiement

- Hébergement mutualisé Hostinger. **Séparation stricte** entre le code applicatif
  (hors du webroot) et le dossier servi publiquement (contient uniquement les
  équivalents du `public/` Laravel : `index.php`, assets buildés, lien symbolique
  vers le stockage public).
- Procédure de mise à jour : `git push` → pull côté serveur → `php artisan migrate
  --force` si nécessaire → `route:clear`/`config:clear`/`view:clear` → si le
  frontend a changé, `npm run build` en local puis upload du dossier `build/` sous un
  nom temporaire, vérification que le nombre de fichiers correspond exactement au
  build local, puis **bascule atomique** (renommage) vers le nom final — jamais un
  upload direct qui pourrait laisser le site dans un état incohérent pendant le
  transfert.
- Avant toute mise en production d'un changement touchant la base de données : script
  PHP autonome enveloppé dans une transaction (`DB::beginTransaction()` /
  `DB::rollBack()` dans un `finally`) exécuté sur le serveur réel pour valider la
  logique contre des données réelles sans jamais rien persister — remplace les tests
  PHPUnit habituels quand l'environnement de production n'a pas les dépendances de
  développement installées (`composer install --no-dev`).
- **Ne jamais inscrire de mot de passe/identifiant de connexion réel dans ce fichier
  ni dans le dépôt Git** — à conserver séparément (gestionnaire de mots de passe). Ce
  document décrit la structure et la procédure, pas les accès eux-mêmes.

---

## 9. Comment utiliser ce document pour reconstruire

1. Initialiser un projet Laravel 12 + Jetstream (stack Inertia, guard sanctum) +
   Fortify avec les features listées en §2.
2. Installer Inertia v2 + Vue 3 + Tailwind + vue-i18n + Ziggy côté frontend.
3. Construire le modèle de données du §4 dans l'ordre des dépendances (users →
   boutiques → produits/clients → factures/lignes → le reste), en respectant les
   contraintes d'unicité et les FK `nullOnDelete`/`cascadeOnDelete` mentionnées.
4. Implémenter chaque module du §5 dans un ordre logique de dépendance (auth →
   boutiques → catalogue → facturation → abonnements/paiements → puis les modules de
   croissance : marketplace, parrainage, partage/abonnés, WhatsApp, CRM).
5. Appliquer les conventions du §3 et la sécurité du §7 **dès le premier module**, pas
   en couche a posteriori — elles conditionnent la forme du code, pas juste son
   comportement.
6. À chaque étape significative, mettre à jour ce fichier (nouvelle entrée en §10,
   ajustement des sections concernées) avant de considérer la tâche terminée.

---

## 10. Historique des évolutions majeures

*(nouvelle entrée en haut, la plus récente en premier — une ligne suffit sauf
changement de comportement significatif)*

- **2026-09-20** — Création de ce fichier de reconstruction, après un audit complet
  du projet existant (34 modèles, 33 contrôleurs, 61 migrations, 95 pages Vue, 42
  fichiers de test). Durcissement sécurité (2FA admin obligatoire, remember-me 30j,
  en-têtes de sécurité). Système de parrainage & commissions (5 % récurrent, modèle
  grand livre). Visibilité par produit étendue au lien direct de boutique (plus
  seulement la marketplace). Lien "Voir la boutique" sur les notifications de nouveau
  produit. Correctif affichage WhatsApp Super Admin (repli sur le numéro de la
  boutique). Partage de boutique, abonnés, popup d'invitation. Essai gratuit 7 jours
  + notifications de rappel. Facture vs Proforma. Connecteur WhatsApp (QR,
  non-officiel) pour l'Agent IA.
