<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\StoreContactWhatsappContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Services\NormalisationTelephoneService;
use App\Services\WhatsappRelanceService;
use App\Support\WhatsappModeles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('telephone', 'like', "%{$recherche}%")
                        ->orWhere('whatsapp', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('statutWhatsapp')->toString(), fn ($q, $v) => $q->where('statut_whatsapp', $v))
            ->when($request->string('statutCommercial')->toString(), fn ($q, $v) => $q->where('statut_commercial', $v))
            ->when($request->string('source')->toString(), fn ($q, $v) => $q->where('source', $v))
            ->when($request->filled('compteLie'), fn ($q) => $request->boolean('compteLie') ? $q->whereNotNull('utilisateur_id') : $q->whereNull('utilisateur_id'))
            ->when($request->boolean('jamaisRelance'), fn ($q) => $q->whereNull('dernier_contact_a'))
            ->with('utilisateur:id,name')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Contacts/Index', [
            'contacts' => $contacts,
            'filtres' => $request->only(['recherche', 'statutWhatsapp', 'statutCommercial', 'source', 'compteLie', 'jamaisRelance']),
            'statistiques' => $this->statistiques(),
            'statutsWhatsapp' => Contact::libellesStatutWhatsapp(),
            'statutsCommerciaux' => Contact::libellesStatutCommercial(),
            'modelesWhatsapp' => WhatsappModeles::liste(),
            'permissionsContacts' => $this->permissionsContacts($request),
        ]);
    }

    public function statutCommercialGroupe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('contacts.modifier'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'statut_commercial' => ['required', Rule::in(Contact::STATUTS_COMMERCIAUX)],
        ]);

        Contact::whereIn('id', $data['ids'])->update(['statut_commercial' => $data['statut_commercial']]);

        return back()->with('flash_success', count($data['ids']).' contact(s) mis à jour.');
    }

    public function store(StoreContactRequest $request, NormalisationTelephoneService $normalisation): RedirectResponse
    {
        $data = $request->validated();
        $numero = $normalisation->normaliser($data['whatsapp'] ?? $data['telephone'] ?? null);

        Contact::create([
            ...$data,
            'numero_normalise' => $numero['normalise'] ?? null,
            'statut_whatsapp' => $numero && ! $numero['valide'] ? Contact::STATUT_WHATSAPP_NUMERO_INVALIDE : Contact::STATUT_WHATSAPP_INCONNU,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('flash_success', 'Contact ajouté.');
    }

    public function show(Request $request, Contact $contact): Response
    {
        return Inertia::render('Admin/Contacts/Show', [
            'contact' => $contact->load('utilisateur:id,name,email', 'createur:id,name'),
            'logsWhatsapp' => $contact->whatsappLogs()->with('admin:id,name')->limit(20)->get(),
            'statutsWhatsapp' => Contact::libellesStatutWhatsapp(),
            'statutsCommerciaux' => Contact::libellesStatutCommercial(),
            'modelesWhatsapp' => WhatsappModeles::liste(),
            'permissionsContacts' => $this->permissionsContacts($request),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact, NormalisationTelephoneService $normalisation): RedirectResponse
    {
        $data = $request->validated();
        $numero = $normalisation->normaliser($data['whatsapp'] ?? $data['telephone'] ?? null);

        $contact->update([
            ...$data,
            'numero_normalise' => $numero['normalise'] ?? $contact->numero_normalise,
        ]);

        return back()->with('flash_success', 'Contact mis à jour.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('contacts.supprimer'), 403);

        $contact->delete();

        return back()->with('flash_success', 'Contact supprimé.');
    }

    public function updateStatutCommercial(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('contacts.modifier'), 403);

        $data = $request->validate(['statut_commercial' => ['required', Rule::in(Contact::STATUTS_COMMERCIAUX)]]);
        $contact->update($data);

        return back()->with('flash_success', 'Statut commercial mis à jour.');
    }

    public function updateStatutWhatsapp(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($request->user()->hasAdminPermission('contacts.modifier'), 403);

        $data = $request->validate(['statut_whatsapp' => ['required', Rule::in(Contact::STATUTS_WHATSAPP)]]);
        $contact->update($data);

        return back()->with('flash_success', 'Statut WhatsApp mis à jour.');
    }

    public function whatsappContacter(StoreContactWhatsappContactRequest $request, Contact $contact, WhatsappRelanceService $relance): JsonResponse
    {
        $numero = $contact->whatsapp ?: $contact->telephone;
        abort_if(! $numero, 422, "Ce contact n'a pas de numéro renseigné.");

        $message = $request->string('message')->toString();
        $log = $relance->contacter($numero, $message, $request->string('modele_cle')->toString() ?: null, ['contact_id' => $contact->id], $request->user());

        $contact->update(['dernier_contact_a' => now()]);

        return response()->json([
            'id' => $log->id,
            'lien' => $relance->lien($numero, $message),
        ]);
    }

    private function permissionsContacts(Request $request): array
    {
        return [
            'voir' => $request->user()->hasAdminPermission('contacts.voir'),
            'importer' => $request->user()->hasAdminPermission('contacts.importer'),
            'exporter' => $request->user()->hasAdminPermission('contacts.exporter'),
            'modifier' => $request->user()->hasAdminPermission('contacts.modifier'),
            'supprimer' => $request->user()->hasAdminPermission('contacts.supprimer'),
            'whatsapp_contacter' => $request->user()->hasAdminPermission('contacts.whatsapp_contacter'),
        ];
    }

    private function statistiques(): array
    {
        return [
            'total' => Contact::count(),
            'sur_whatsapp' => Contact::where('statut_whatsapp', Contact::STATUT_WHATSAPP_SUR_WHATSAPP)->count(),
            'comptes_lies' => Contact::whereNotNull('utilisateur_id')->count(),
            'whatsapp_sans_compte' => Contact::where('statut_whatsapp', Contact::STATUT_WHATSAPP_SUR_WHATSAPP)->whereNull('utilisateur_id')->count(),
        ];
    }
}
