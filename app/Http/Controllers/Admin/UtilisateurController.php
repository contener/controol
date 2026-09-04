<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Jetstream\DeleteUser;
use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Boutique;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion des utilisateurs finaux (boutiquiers) par le Super Administrateur — distincte
 * de Admin\AdministrateurController qui gère les comptes role=admin. Chaque route est
 * protégée par une permission granulaire ('utilisateurs.voir/suspendre/supprimer', voir
 * App\Support\AdminPermissions), jamais par le seul rôle super_admin — sauf pour les
 * actions destructrices qui restent volontairement soumises à une permission explicite,
 * cohérent avec le cahier des charges d'origine sur la gestion des administrateurs.
 */
class UtilisateurController extends Controller
{
    public function index(Request $request): Response
    {
        $utilisateurs = User::where('role', User::ROLE_USER)
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('statut')->toString(), function ($query, $statut) {
                $query->where('est_actif', $statut === 'actif');
            })
            ->withCount('boutiques')
            ->orderBy($request->string('tri', 'created_at')->toString(), $request->string('direction', 'desc')->toString())
            ->paginate(15)
            ->withQueryString();

        $utilisateurs->getCollection()->transform(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'est_actif' => $u->est_actif,
            'boutiques_count' => $u->boutiques_count,
            'plan' => $u->planActif()?->nom,
            'created_at' => $u->created_at,
        ]);

        return Inertia::render('Admin/Utilisateurs/Index', [
            'utilisateurs' => $utilisateurs,
            'filtres' => $request->only(['recherche', 'statut', 'tri', 'direction']),
        ]);
    }

    public function show(User $utilisateur): Response
    {
        $this->assertEstUnUtilisateurGere($utilisateur);

        // withoutGlobalScopes() est indispensable ici : Client/Produit/Facture utilisent
        // BelongsToBoutique, dont le scope global fail-closed filtre par la boutique
        // COURANTE de l'utilisateur connecté (le Super Admin), pas celle du compte
        // consulté — sans ce contournement explicite, les compteurs afficheraient
        // toujours 0 pour un Super Admin sans boutique courante.
        $boutiques = $utilisateur->boutiques()
            ->withCount([
                'clients' => fn ($q) => $q->withoutGlobalScopes(),
                'produits' => fn ($q) => $q->withoutGlobalScopes(),
                'factures' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->latest()
            ->get();

        return Inertia::render('Admin/Utilisateurs/Show', [
            'utilisateur' => [
                'id' => $utilisateur->id,
                'name' => $utilisateur->name,
                'email' => $utilisateur->email,
                'telephone' => $utilisateur->telephone,
                'ville' => $utilisateur->ville,
                'est_actif' => $utilisateur->est_actif,
                'created_at' => $utilisateur->created_at,
                'plan' => $utilisateur->planActif()?->nom,
            ],
            'boutiques' => $boutiques,
            'audits' => AdminAudit::where('resource', 'utilisateur')
                ->where('resource_id', $utilisateur->id)
                ->with('admin:id,name')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function basculerActivation(Request $request, User $utilisateur): RedirectResponse
    {
        $this->assertEstUnUtilisateurGere($utilisateur);

        $ancienStatut = $utilisateur->est_actif;
        $utilisateur->forceFill(['est_actif' => ! $ancienStatut])->save();

        if (! $utilisateur->est_actif) {
            // Coupe toute session déjà ouverte immédiatement, sans attendre que
            // EnsureAccountActive l'intercepte à sa prochaine requête.
            DB::table('sessions')->where('user_id', $utilisateur->id)->delete();
        }

        $this->journaliser(
            $request,
            $utilisateur->est_actif ? 'utilisateur_reactive' : 'utilisateur_desactive',
            $utilisateur,
            ['est_actif' => $ancienStatut],
            ['est_actif' => $utilisateur->est_actif],
        );

        $message = $utilisateur->est_actif
            ? "{$utilisateur->name} a été réactivé."
            : "{$utilisateur->name} a été désactivé et déconnecté.";

        return back()->with('flash_success', $message);
    }

    public function destroyBoutique(Request $request, User $utilisateur, Boutique $boutique): RedirectResponse
    {
        $this->assertEstUnUtilisateurGere($utilisateur);
        abort_unless($boutique->user_id === $utilisateur->id, 404);

        $nom = $boutique->nom;
        $boutique->delete();

        $this->journaliser($request, 'boutique_supprimee', $utilisateur, ['boutique' => $nom], null);

        return back()->with('flash_success', "Boutique \"{$nom}\" supprimée.");
    }

    public function destroy(Request $request, User $utilisateur, DeleteUser $deleteUser): RedirectResponse
    {
        $this->assertEstUnUtilisateurGere($utilisateur);

        $nom = $utilisateur->name;
        $email = $utilisateur->email;

        // Journalisé AVANT suppression : resource_id n'est pas une clé étrangère (voir
        // migration create_admin_audits_table), l'historique reste donc lisible même une
        // fois le compte réellement supprimé.
        AdminAudit::create([
            'admin_id' => $request->user()->id,
            'action' => 'utilisateur_supprime',
            'resource' => 'utilisateur',
            'resource_id' => $utilisateur->id,
            'ancienne_valeur' => ['name' => $nom, 'email' => $email],
            'nouvelle_valeur' => null,
            'ip_address' => $request->ip(),
        ]);

        $deleteUser->delete($utilisateur);

        return redirect()->route('admin.utilisateurs.index')->with('flash_success', "Le compte de {$nom} ({$email}) a été supprimé définitivement.");
    }

    /**
     * Empêche cette page de charger/modifier n'importe quel User par manipulation
     * d'URL — un Super Admin ne peut jamais désactiver/supprimer un compte administrateur
     * ou son propre compte via cette route, uniquement de vrais comptes role=user.
     */
    private function assertEstUnUtilisateurGere(User $utilisateur): void
    {
        abort_unless($utilisateur->role === User::ROLE_USER, 404);
    }

    private function journaliser(Request $request, string $action, User $utilisateur, ?array $ancienneValeur, ?array $nouvelleValeur): void
    {
        AdminAudit::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'resource' => 'utilisateur',
            'resource_id' => $utilisateur->id,
            'ancienne_valeur' => $ancienneValeur,
            'nouvelle_valeur' => $nouvelleValeur,
            'ip_address' => $request->ip(),
        ]);
    }
}
