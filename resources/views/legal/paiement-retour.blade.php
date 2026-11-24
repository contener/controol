@extends('legal.layout')

@section('titre', $confirme ? 'Paiement confirmé' : 'Paiement en cours de vérification')
@section('description', 'Retour du paiement de votre abonnement Controool.')

@section('contenu')
    @if ($confirme)
        <div class="encart">
            <strong>Merci !</strong> Votre paiement a été confirmé et votre abonnement est actif.
        </div>
    @else
        <div class="encart">
            Votre paiement est en cours de vérification auprès du service de paiement.
            Votre abonnement sera activé automatiquement dès sa confirmation.
        </div>
    @endif
    <p>Vous pouvez revenir dans l'application Controool.</p>
@endsection
