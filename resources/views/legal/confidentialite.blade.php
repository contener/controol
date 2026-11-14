@extends('legal.layout')

@section('titre', 'Politique de confidentialité')
@section('description', 'Comment Controool collecte, utilise et protège vos données personnelles, sur le site controol.fr et dans l\'application mobile.')

@section('contenu')
    <h2>1. Qui sommes-nous ?</h2>
    <p>
        Controool est une plateforme de gestion commerciale (boutiques, produits, stock, clients, factures, dépenses,
        boutique publique et Marketplace), accessible sur le site <a href="{{ url('/') }}">controol.fr</a> et via
        l'application mobile Android « Controool ». Cette politique explique quelles données nous traitons, pourquoi,
        avec qui elles sont partagées et quels sont vos droits. Pour toute question :
        <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>.
    </p>

    <h2>2. Données que nous collectons</h2>
    <p><strong>Votre compte</strong> : nom, adresse e-mail, mot de passe (stocké chiffré, jamais en clair), et si vous les
        renseignez : téléphone, numéro WhatsApp, ville, date de naissance, photo de profil.</p>
    <p><strong>Votre activité sur la plateforme</strong> : informations de vos boutiques (nom, coordonnées, logo, bannière,
        NUI, liens de réseaux sociaux), produits et services (dont photos et prix), stock, clients, factures, devis, reçus,
        dépenses, collaborateurs que vous invitez, messages échangés avec vos clients ou d'autres boutiques.</p>
    <p><strong>Données de vos propres clients</strong> : lorsque vous enregistrez vos clients (nom, contact, adresse, NUI,
        RCCM...) ou que des visiteurs écrivent à votre boutique, vous restez responsable de ces données ; Controool les
        héberge et les traite uniquement pour vous fournir le service.</p>
    <p><strong>Interactions avec les boutiques publiques</strong> : « J'aime », messages, abonnements à une boutique et
        visites, afin de permettre au vendeur de suivre l'intérêt pour ses produits (rubrique « Audience »). Pour un visiteur
        non connecté, un identifiant aléatoire est conservé dans un cookie.</p>
    <p><strong>Abonnements et paiements</strong> : plan choisi, montant, statut et historique des paiements, essais gratuits,
        parrainages et commissions. Les paiements sont réalisés auprès d'un prestataire externe : nous ne recevons ni ne
        conservons jamais vos numéros de carte ou identifiants de paiement.</p>
    <p><strong>Données techniques</strong> : adresse IP, journaux de connexion et de sécurité, cookies de session nécessaires
        au fonctionnement du site, préférences (langue, thème).</p>

    <h2>3. L'application mobile</h2>
    <ul>
        <li>Elle ne demande que l'accès à Internet. Les photos (produits, logo, profil) ne sont lues que lorsque
            <strong>vous</strong> en choisissez une ou en prenez une ; aucun accès permanent à vos fichiers.</li>
        <li>Elle n'accède ni à votre position, ni à vos contacts, ni au micro.</li>
        <li>Votre jeton de connexion est conservé chiffré par le système (Keystore Android) ; il expire et est révoqué à la
            déconnexion ou au changement de mot de passe.</li>
        <li>Aucune publicité, aucun outil de suivi publicitaire, aucune revente de données.</li>
    </ul>

    <h2>4. Pourquoi nous utilisons vos données</h2>
    <ul>
        <li>Fournir le service : créer votre compte et votre boutique, gérer vos produits, clients, factures et stocks,
            afficher votre boutique publique et la Marketplace (base : exécution du contrat).</li>
        <li>Gérer les abonnements, les essais, le parrainage et vérifier les paiements (exécution du contrat).</li>
        <li>Vous envoyer des notifications liées au service (nouveaux messages, nouveaux produits des boutiques suivies,
            fin d'essai) et, si nous vous contactons, par e-mail ou WhatsApp (intérêt légitime).</li>
        <li>Sécuriser la plateforme : prévention de la fraude et des abus, limitation des tentatives de connexion, journaux
            d'audit des actions d'administration (intérêt légitime, obligations légales).</li>
    </ul>

    <h2>5. Avec qui vos données sont partagées</h2>
    <ul>
        <li><strong>Public</strong> : les informations de votre boutique publique et des produits que vous rendez visibles
            (nom, photos, prix de vente, coordonnées de la boutique) sont accessibles à tous. Le prix d'achat, le stock et
            vos données internes ne sont jamais publiés.</li>
        <li><strong>Vos collaborateurs</strong> : uniquement les rubriques auxquelles vous leur donnez accès.</li>
        <li><strong>Prestataires techniques</strong> : notre hébergeur (serveurs et base de données), le prestataire de
            paiement lorsque vous payez un abonnement, Google si vous choisissez « Continuer avec Google ».</li>
        <li><strong>WhatsApp, Facebook, Telegram</strong> : uniquement lorsque vous ouvrez vous-même un lien de partage ou de
            contact ; aucune donnée ne leur est transmise automatiquement.</li>
        <li>Les autorités, uniquement lorsque la loi l'exige.</li>
    </ul>
    <p>Nous ne vendons jamais vos données personnelles.</p>

    <h2>6. Durée de conservation</h2>
    <p>Vos données sont conservées tant que votre compte existe. Lorsque vous supprimez votre compte, il est effacé
        immédiatement et définitivement, avec vos boutiques et leurs données (voir
        <a href="{{ route('legal.suppression-compte') }}">Supprimer son compte</a>). Les journaux techniques de sécurité
        sont conservés pour une durée limitée, nécessaire à la protection de la plateforme.</p>

    <h2>7. Sécurité</h2>
    <p>Connexions chiffrées (HTTPS), mots de passe hachés, double authentification disponible (obligatoire pour les
        administrateurs), cloisonnement strict des données entre boutiques, contrôle des accès sur chaque action et
        audits de sécurité réguliers.</p>

    <h2>8. Vos droits</h2>
    <p>Vous pouvez à tout moment accéder à vos données et les corriger (Profil, Mes boutiques), supprimer votre compte
        depuis l'application ou le site, retirer votre consentement aux communications, ou demander une copie de vos
        données en écrivant à <a href="mailto:{{ config('app.contact_email') }}">{{ config('app.contact_email') }}</a>.
        Nous répondons dans un délai d'un mois. Ces droits s'exercent conformément à la législation applicable en matière
        de protection des données personnelles.</p>

    <h2>9. Mineurs</h2>
    <p>Controool est un outil professionnel destiné aux personnes majeures ; il ne s'adresse pas aux enfants.</p>

    <h2>10. Modifications</h2>
    <p>Nous pouvons mettre à jour cette politique ; la date de dernière mise à jour figure en haut de cette page. En cas de
        changement important, vous en serez informé dans l'application ou par e-mail.</p>
@endsection
