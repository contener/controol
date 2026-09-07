<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Boutique;
use App\Models\Message;
use App\Models\Produit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicBoutiqueController extends Controller
{
    /**
     * Champs réellement destinés à être publics. On ne sérialise jamais le modèle
     * Boutique brut ici : user_id, marketplace_visible, marketplace_disabled_by_admin,
     * taux_tva_defaut ne doivent jamais transiter vers une page anonyme.
     */
    private const CHAMPS_PUBLICS = [
        'id', 'nom', 'slug', 'logo_path', 'banniere_path', 'description', 'categorie',
        'adresse', 'ville', 'pays', 'telephone', 'whatsapp', 'email', 'devise',
        'facebook_url', 'instagram_url', 'telegram_url',
    ];

    public function show(string $slug, Request $request): Response
    {
        $boutique = $this->resoudreBoutique($slug);

        $produits = $boutique->produits()
            ->withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->when($request->string('categorie')->toString(), fn ($q, $c) => $q->where('categorie', $c))
            ->orderBy('nom')
            ->get();

        $categories = $boutique->produits()
            ->withoutGlobalScopes()
            ->where('boutique_id', $boutique->id)
            ->where('actif', true)
            ->whereNotNull('categorie')
            ->distinct()
            ->pluck('categorie');

        return Inertia::render('Public/Boutique', [
            'boutique' => $boutique->only(self::CHAMPS_PUBLICS),
            'produits' => $produits,
            'categories' => $categories,
            'filtres' => $request->only('categorie'),
            'meta' => $this->meta($boutique),
        ]);
    }

    public function envoyerMessage(StoreMessageRequest $request, string $slug): RedirectResponse
    {
        $boutique = $this->resoudreBoutique($slug);

        // Un produit forgé appartenant à une autre boutique ne doit jamais rattacher le
        // message à un mauvais vendeur — on le résout uniquement dans le périmètre de
        // cette boutique, sinon on laisse le message sans produit associé.
        $produit = $request->filled('produit_id')
            ? Produit::withoutGlobalScopes()->where('boutique_id', $boutique->id)->find($request->integer('produit_id'))
            : null;

        Message::create([
            'boutique_id' => $boutique->id,
            'produit_id' => $produit?->id,
            'nom_visiteur' => $request->string('nom_visiteur')->toString(),
            'contact_visiteur' => $request->string('contact_visiteur')->toString() ?: null,
            'contenu' => $request->string('contenu')->toString(),
        ]);

        return back()->with('flash_success', 'Votre message a bien été envoyé au vendeur.');
    }

    private function resoudreBoutique(string $slug): Boutique
    {
        return Boutique::where('slug', $slug)->where('statut', 'active')->firstOrFail();
    }

    private function meta(Boutique $boutique): array
    {
        $image = $boutique->banniere_path ?? $boutique->logo_path;

        return [
            'title' => $boutique->nom,
            'description' => $boutique->description
                ? Str::limit(strip_tags($boutique->description), 160)
                : "Découvrez {$boutique->nom} et ses produits.",
            'image' => $image ? Storage::disk('public')->url($image) : null,
            'url' => route('public.boutique', $boutique->slug),
            'type' => 'website',
        ];
    }
}
