<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmerCampagneDestinationRequest;
use App\Http\Requests\DemarrerCampagneRequest;
use App\Models\CampagneDestination;
use App\Models\CampagneSociale;
use App\Models\DestinationSociale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Aucune publication n'est jamais automatisée vers Facebook/WhatsApp/Messenger/Telegram
 * ici : cet outil génère un message prêt à copier et guide l'utilisateur destination par
 * destination. Le statut "envoyé" n'est écrit qu'après confirmation humaine explicite —
 * jamais déduit automatiquement d'un appel réseau. Voir le plan pour le contexte complet.
 */
class CampagneSocialeController extends Controller
{
    public function demarrer(DemarrerCampagneRequest $request): RedirectResponse
    {
        $boutique = $request->user()->currentBoutique;

        $destinations = DestinationSociale::orderBy('ordre')->get();

        if ($destinations->isEmpty()) {
            return back()->with('flash_error', 'Ajoutez au moins une destination avant de démarrer une campagne.');
        }

        DB::transaction(function () use ($request, $boutique, $destinations) {
            $campagne = CampagneSociale::create([
                'boutique_id' => $boutique->id,
                'message' => $request->validated('message'),
                'statut' => 'en_cours',
                'intervalle_secondes' => $request->validated('intervalle_secondes'),
                'created_by' => $request->user()->id,
                'started_at' => now(),
            ]);

            foreach ($destinations as $destination) {
                CampagneDestination::create([
                    'campagne_id' => $campagne->id,
                    'destination_sociale_id' => $destination->id,
                    'statut' => 'en_attente',
                ]);
                $destination->update(['statut' => 'en_attente']);
            }
        });

        return back()->with('flash_success', 'Campagne démarrée. Suivez le guide pour publier destination par destination.');
    }

    public function confirmerDestination(ConfirmerCampagneDestinationRequest $request, CampagneDestination $campagneDestination): RedirectResponse
    {
        $statut = $request->validated('statut');

        $campagneDestination->update(['statut' => $statut, 'traite_at' => now()]);
        $campagneDestination->destination->update(['statut' => $statut]);

        $campagne = $campagneDestination->campagne;

        $resteEnAttente = $campagne->campagneDestinations()->whereIn('statut', ['en_attente', 'en_cours'])->exists();

        if (! $resteEnAttente) {
            $campagne->update(['statut' => 'terminee', 'ended_at' => now()]);
        }

        return back()->with('flash_success', 'Statut de la destination mis à jour.');
    }

    public function arreter(CampagneSociale $campagneSociale): RedirectResponse
    {
        $this->authorize('update', $campagneSociale);

        $campagneSociale->update(['statut' => 'arretee', 'ended_at' => now()]);

        return back()->with('flash_success', 'Campagne arrêtée.');
    }
}
