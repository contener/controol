<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollaborateurRequest;
use App\Http\Requests\UpdateCollaborateurPermissionsRequest;
use App\Models\Boutique;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Équivalent API de BoutiqueCollaborateurController (page "Équipe") -- mêmes
 * FormRequests (propriété stricte + plan personnel_boutique), même création de
 * compte, même révocation immédiate de la boutique courante au retrait.
 */
class EquipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique && $boutique->user_id === $request->user()->id, 403, "Seul le propriétaire de la boutique gère l'équipe.");

        $autorise = (bool) $request->user()->planActif()?->personnel_boutique;

        return response()->json([
            'autorise' => $autorise,
            'permissions_disponibles' => Boutique::PERMISSIONS_COLLABORATEUR,
            'collaborateurs' => $autorise
                ? $boutique->collaborateurs()->orderBy('name')->get()->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'telephone' => $u->telephone,
                    'profile_photo_url' => $u->profile_photo_url,
                    'ajoute_le' => $u->pivot->created_at->format('d/m/Y'),
                    'permissions' => collect(Boutique::PERMISSIONS_COLLABORATEUR)->mapWithKeys(fn ($c) => [$c => (bool) $u->pivot->{$c}]),
                ])
                : [],
        ]);
    }

    public function store(StoreCollaborateurRequest $request): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        $data = $request->validated();

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

        $boutique->collaborateurs()->attach($collaborateur->id, $this->permissionsCochees($data));

        return response()->json(['message' => "{$collaborateur->name} a été ajouté comme collaborateur de {$boutique->nom}."], 201);
    }

    public function update(UpdateCollaborateurPermissionsRequest $request, User $collaborateur): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique->collaborateurs()->whereKey($collaborateur->id)->exists(), 404);

        $boutique->collaborateurs()->updateExistingPivot($collaborateur->id, $this->permissionsCochees($request->validated()));

        return response()->json(['message' => "Accès de {$collaborateur->name} mis à jour."]);
    }

    public function destroy(Request $request, User $collaborateur): JsonResponse
    {
        $boutique = $request->user()->currentBoutique;
        abort_unless($boutique && $boutique->user_id === $request->user()->id, 403);
        abort_unless($boutique->collaborateurs()->whereKey($collaborateur->id)->exists(), 404);

        $boutique->collaborateurs()->detach($collaborateur->id);

        if ($collaborateur->current_boutique_id === $boutique->id) {
            $collaborateur->forceFill(['current_boutique_id' => null])->save();
        }

        return response()->json(['message' => "{$collaborateur->name} n'a plus accès à {$boutique->nom}."]);
    }

    private function permissionsCochees(array $data): array
    {
        $permissions = [];
        foreach (Boutique::PERMISSIONS_COLLABORATEUR as $colonne) {
            $permissions[$colonne] = (bool) ($data[$colonne] ?? false);
        }

        return $permissions;
    }
}
