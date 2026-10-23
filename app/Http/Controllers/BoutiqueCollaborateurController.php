<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollaborateurRequest;
use App\Http\Requests\UpdateCollaborateurPermissionsRequest;
use App\Models\Boutique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion des collaborateurs d'une boutique (plan Basique/Pro, plans.personnel_boutique)
 * -- réservé au seul propriétaire, jamais à un collaborateur même avec un accès complet
 * par ailleurs (voir Boutique::estGereePar()), pour éviter toute chaîne d'escalade de
 * privilège. Chaque action vérifie explicitement la propriété stricte de la boutique
 * courante, jamais seulement estGereePar().
 */
class BoutiqueCollaborateurController extends Controller
{
    public function index(Request $request): Response
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique->user_id === $request->user()->id, 403);

        $autorise = (bool) $request->user()->planActif()?->personnel_boutique;

        return Inertia::render('Boutiques/Collaborateurs', [
            'autorise' => $autorise,
            'permissionsDisponibles' => Boutique::PERMISSIONS_COLLABORATEUR,
            'collaborateurs' => $autorise
                ? $boutique->collaborateurs()->orderBy('name')->get()->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'telephone' => $u->telephone,
                    'ajoute_le' => $u->pivot->created_at->format('d/m/Y'),
                    'permissions' => $this->permissionsDuPivot($u),
                ])
                : [],
        ]);
    }

    public function store(StoreCollaborateurRequest $request): RedirectResponse
    {
        $boutique = $request->user()->currentBoutique;
        $data = $request->validated();

        // email_verified_at renseigné d'emblée : le propriétaire se porte garant de ce
        // compte qu'il vient de créer lui-même -- jamais d'essai gratuit/abonnement
        // propre, ce compte opère sous le plan du propriétaire (voir User::planActif()).
        $collaborateur = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);
        $collaborateur->forceFill([
            'email_verified_at' => now(),
            'current_boutique_id' => $boutique->id,
        ])->save();

        // Chaque permission omise ou décochée reste à false (défaut colonne) -- jamais
        // d'accès automatique, même pour les fonctionnalités non cochées explicitement.
        $boutique->collaborateurs()->attach($collaborateur->id, $this->permissionsCochees($data));

        return back()->with('flash_success', "{$collaborateur->name} a été ajouté comme collaborateur de {$boutique->nom}.");
    }

    public function update(UpdateCollaborateurPermissionsRequest $request, User $collaborateur): RedirectResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique->collaborateurs()->whereKey($collaborateur->id)->exists(), 404);

        $boutique->collaborateurs()->updateExistingPivot($collaborateur->id, $this->permissionsCochees($request->validated()));

        return back()->with('flash_success', "Accès de {$collaborateur->name} mis à jour.");
    }

    public function destroy(Request $request, User $collaborateur): RedirectResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique->user_id === $request->user()->id, 403);
        abort_unless($boutique->collaborateurs()->whereKey($collaborateur->id)->exists(), 404);

        $boutique->collaborateurs()->detach($collaborateur->id);

        // Révocation immédiate, pas un nettoyage paresseux à la prochaine requête (même
        // principe que la désactivation d'un compte, qui purge ses sessions actives) --
        // si ce collaborateur avait cette boutique comme boutique courante, il ne doit
        // plus jamais pouvoir continuer à agir sur elle.
        if ($collaborateur->current_boutique_id === $boutique->id) {
            $collaborateur->forceFill(['current_boutique_id' => null])->save();
        }

        return back()->with('flash_success', "{$collaborateur->name} n'a plus accès à {$boutique->nom}.");
    }

    private function permissionsCochees(array $data): array
    {
        $permissions = [];
        foreach (Boutique::PERMISSIONS_COLLABORATEUR as $colonne) {
            $permissions[$colonne] = (bool) ($data[$colonne] ?? false);
        }

        return $permissions;
    }

    private function permissionsDuPivot(User $u): array
    {
        $permissions = [];
        foreach (Boutique::PERMISSIONS_COLLABORATEUR as $colonne) {
            $permissions[$colonne] = (bool) $u->pivot->{$colonne};
        }

        return $permissions;
    }
}
