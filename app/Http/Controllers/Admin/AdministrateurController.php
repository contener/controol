<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdministrateurRequest;
use App\Http\Requests\UpdateAdministrateurRequest;
use App\Models\AdminAudit;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Toutes les routes de ce contrôleur sont protégées par le middleware 'super_admin'
 * (voir routes/web.php, groupe /admin/administrateurs) — gérer des administrateurs
 * reste un privilège non-délégable, jamais accordé via une permission granulaire.
 */
class AdministrateurController extends Controller
{
    public function index(Request $request): Response
    {
        $administrateurs = User::where('role', User::ROLE_ADMIN)
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('statut')->toString(), function ($query, $statut) {
                $query->where('est_actif', $statut === 'actif');
            })
            ->withCount('adminPermissions')
            ->orderBy($request->string('tri', 'created_at')->toString(), $request->string('direction', 'desc')->toString())
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Administrateurs/Index', [
            'administrateurs' => $administrateurs,
            'filtres' => $request->only(['recherche', 'statut', 'tri', 'direction']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Administrateurs/Create', [
            'groupesPermissions' => AdminPermissions::GROUPES,
            'roles' => AdminPermissions::libellesRoles(),
            'presets' => AdminPermissions::PRESETS_PAR_ROLE,
        ]);
    }

    public function store(StoreAdministrateurRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $administrateur = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        // role/admin_role_label/est_actif sont volontairement absents de $fillable sur
        // User (même principe que le rôle super_admin) — assignation explicite ici, dans
        // le seul contrôleur autorisé à créer des administrateurs.
        $administrateur->forceFill([
            'role' => User::ROLE_ADMIN,
            'admin_role_label' => $data['admin_role_label'],
            'est_actif' => true,
        ])->save();

        $permissions = $data['permissions'] ?? [];
        foreach ($permissions as $permission) {
            $administrateur->adminPermissions()->create(['permission' => $permission]);
        }

        $this->journaliser($request, 'administrateur_cree', $administrateur, null, [
            'admin_role_label' => $data['admin_role_label'],
            'permissions' => $permissions,
        ]);

        return redirect()->route('admin.administrateurs.index')->with('flash_success', "Administrateur {$administrateur->name} créé avec succès.");
    }

    public function edit(User $administrateur): Response
    {
        $this->assertEstUnAdministrateurGere($administrateur);

        return Inertia::render('Admin/Administrateurs/Edit', [
            'administrateur' => [
                'id' => $administrateur->id,
                'name' => $administrateur->name,
                'email' => $administrateur->email,
                'telephone' => $administrateur->telephone,
                'admin_role_label' => $administrateur->admin_role_label,
                'est_actif' => $administrateur->est_actif,
                'created_at' => $administrateur->created_at,
                'permissions' => $administrateur->adminPermissions->pluck('permission'),
            ],
            'groupesPermissions' => AdminPermissions::GROUPES,
            'roles' => AdminPermissions::libellesRoles(),
            'presets' => AdminPermissions::PRESETS_PAR_ROLE,
            'audits' => AdminAudit::where('resource', 'administrateur')
                ->where('resource_id', $administrateur->id)
                ->with('admin:id,name')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function update(UpdateAdministrateurRequest $request, User $administrateur): RedirectResponse
    {
        $this->assertEstUnAdministrateurGere($administrateur);

        $data = $request->validated();
        $ancienneValeur = [
            'admin_role_label' => $administrateur->admin_role_label,
            'permissions' => $administrateur->adminPermissions->pluck('permission')->all(),
        ];

        $administrateur->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $administrateur->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        $administrateur->forceFill(['admin_role_label' => $data['admin_role_label']])->save();

        $nouvellesPermissions = $data['permissions'] ?? [];
        $administrateur->adminPermissions()->delete();
        foreach ($nouvellesPermissions as $permission) {
            $administrateur->adminPermissions()->create(['permission' => $permission]);
        }

        $this->journaliser($request, 'administrateur_modifie', $administrateur, $ancienneValeur, [
            'admin_role_label' => $data['admin_role_label'],
            'permissions' => $nouvellesPermissions,
        ]);

        return redirect()->route('admin.administrateurs.index')->with('flash_success', "Administrateur {$administrateur->name} mis à jour.");
    }

    public function basculerActivation(Request $request, User $administrateur): RedirectResponse
    {
        $this->assertEstUnAdministrateurGere($administrateur);

        $ancienStatut = $administrateur->est_actif;
        $administrateur->forceFill(['est_actif' => ! $ancienStatut])->save();

        $this->journaliser(
            $request,
            $administrateur->est_actif ? 'administrateur_reactive' : 'administrateur_desactive',
            $administrateur,
            ['est_actif' => $ancienStatut],
            ['est_actif' => $administrateur->est_actif],
        );

        $message = $administrateur->est_actif
            ? "{$administrateur->name} a été réactivé."
            : "{$administrateur->name} a été désactivé. Il n'a plus accès à l'espace d'administration.";

        return back()->with('flash_success', $message);
    }

    /**
     * Empêche cette page de charger/modifier n'importe quel User par manipulation
     * d'URL — un Super Admin ne peut jamais éditer son propre compte ni celui d'un
     * utilisateur final via cette route, uniquement de vrais comptes role=admin.
     */
    private function assertEstUnAdministrateurGere(User $administrateur): void
    {
        abort_unless($administrateur->role === User::ROLE_ADMIN, 404);
    }

    private function journaliser(Request $request, string $action, User $administrateur, ?array $ancienneValeur, ?array $nouvelleValeur): void
    {
        AdminAudit::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'resource' => 'administrateur',
            'resource_id' => $administrateur->id,
            'ancienne_valeur' => $ancienneValeur,
            'nouvelle_valeur' => $nouvelleValeur,
            'ip_address' => $request->ip(),
        ]);
    }
}
