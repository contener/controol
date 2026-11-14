@extends('legal.layout')

@section('titre', 'Supprimer son compte Controool')
@section('description', 'Comment supprimer votre compte Controool et quelles données sont effacées.')

@section('contenu')
    <h2>Depuis l'application mobile</h2>
    <ol>
        <li>Ouvrez l'application Controool et connectez-vous.</li>
        <li>Allez dans <strong>Compte › Profil</strong>.</li>
        <li>En bas de la page, touchez <strong>« Supprimer mon compte »</strong>.</li>
        <li>Saisissez votre mot de passe et confirmez.</li>
    </ol>

    <h2>Depuis le site controol.fr</h2>
    <ol>
        <li>Connectez-vous sur <a href="{{ url('/login') }}">controol.fr</a>.</li>
        <li>Ouvrez votre <strong>Profil</strong> (menu de votre compte, en haut à droite).</li>
        <li>Dans la section <strong>« Supprimer le compte »</strong>, cliquez sur le bouton et confirmez avec votre mot de passe.</li>
    </ol>

    <h2>Vous ne pouvez plus vous connecter ?</h2>
    <p>Écrivez-nous à <a href="mailto:{{ config('app.contact_email') }}?subject=Suppression%20de%20mon%20compte">{{ config('app.contact_email') }}</a>
        depuis l'adresse e-mail de votre compte, avec l'objet « Suppression de mon compte ». Nous vérifions que la demande
        vient bien du titulaire du compte, puis nous le supprimons dans un délai de 30 jours.</p>

    <h2>Quelles données sont supprimées ?</h2>
    <div class="encart alerte">
        <strong>La suppression est immédiate et définitive.</strong> Elle ne peut pas être annulée.
    </div>
    <ul>
        <li>Votre compte : nom, e-mail, mot de passe, téléphone, WhatsApp, ville, date de naissance, photo de profil.</li>
        <li><strong>Toutes les boutiques dont vous êtes propriétaire</strong>, avec leurs produits, photos, stock, clients,
            factures, devis, reçus, dépenses, messages, audience et collaborateurs.</li>
        <li>Vos abonnements, historique de paiements, essais, notifications et abonnements à d'autres boutiques.</li>
        <li>Vos connexions à l'application mobile (tous vos appareils sont déconnectés).</li>
    </ul>
    <p>Les messages que vous avez envoyés à d'autres boutiques restent visibles dans leur messagerie, sans lien vers votre
        compte. Pensez à télécharger les PDF de vos factures avant de supprimer votre compte si vous en avez besoin.</p>

    <h2>Collaborateurs</h2>
    <p>Si vous êtes collaborateur d'une boutique, la suppression de votre compte ne supprime pas la boutique de son
        propriétaire : vous perdez simplement votre accès.</p>
@endsection
