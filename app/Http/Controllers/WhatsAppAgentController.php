<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWhatsAppAgentRequest;
use App\Models\Boutique;
use App\Models\WhatsAppAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppAgentController extends Controller
{
    /**
     * Point d'entrée depuis une carte boutique qui n'est pas forcément la boutique courante
     * (ex. Boutiques/Index.vue liste toutes les boutiques de l'utilisateur) — on bascule
     * d'abord dessus, comme BoutiqueController::switch(), avant de rejoindre la page qui,
     * elle, n'opère jamais que sur currentBoutique.
     */
    public function ouvrir(Request $request, Boutique $boutique): RedirectResponse
    {
        $this->authorize('view', $boutique);

        $request->user()->switchBoutique($boutique);

        return redirect()->route('whatsapp-agent.show');
    }

    public function show(Request $request): Response
    {
        $boutique = $request->user()->currentBoutique;
        $agent = $this->agentCourant();

        return Inertia::render('WhatsappAgent/Show', [
            'boutique' => $boutique->only(['id', 'nom']),
            'agent' => $agent,
            'autorise' => $boutique->agentIaAutorise(),
        ]);
    }

    public function update(UpdateWhatsAppAgentRequest $request): RedirectResponse
    {
        $agent = $this->agentCourant();
        $agent->update($request->validated());

        return back()->with('flash_success', 'Configuration de l\'agent mise à jour.');
    }

    public function toggleStatut(Request $request): RedirectResponse
    {
        $data = $request->validate(['actif' => ['required', 'boolean']]);

        $boutique = $request->user()->currentBoutique;
        $agent = $this->agentCourant();

        $autorise = $boutique->agentIaAutorise();
        $agent->update(['actif' => $data['actif'] && $autorise]);

        if ($data['actif'] && ! $autorise) {
            return back()->with('flash_error', "Votre abonnement actuel ne permet pas d'activer l'Agent IA WhatsApp. Passez à un plan supérieur.");
        }

        return back()->with('flash_success', $agent->actif ? 'Agent IA activé.' : 'Agent IA désactivé.');
    }

    private function agentCourant(): WhatsAppAgent
    {
        return WhatsAppAgent::firstOrCreate();
    }
}
