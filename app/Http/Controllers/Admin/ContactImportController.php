<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportContactsAnalyserRequest;
use App\Http\Requests\ImportContactsConfirmerRequest;
use App\Imports\ContactsImportReader;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\User;
use App\Services\NormalisationTelephoneService;
use App\Support\ContactColonnesExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ContactImportController extends Controller
{
    // Garde-fou pour un import synchrone (pas de file d'attente/worker confirmé sur
    // l'hébergement mutuel) — au-delà, un message clair invite à scinder le fichier plutôt
    // que de risquer un dépassement de temps d'exécution ou de mémoire PHP.
    private const LIGNES_MAX = 5000;

    public function index(): Response
    {
        return Inertia::render('Admin/Contacts/Import/Index', [
            'imports' => ContactImport::with('admin:id,name')->latest()->paginate(15),
        ]);
    }

    public function analyser(ImportContactsAnalyserRequest $request, ContactColonnesExcel $colonnes, NormalisationTelephoneService $normalisation): JsonResponse
    {
        $fichier = $request->file('fichier');

        $lecteur = new ContactsImportReader;
        Excel::import($lecteur, $fichier);
        $lignes = $lecteur->lignes;

        abort_if($lignes->count() > self::LIGNES_MAX, 422, "Ce fichier contient plus de ".self::LIGNES_MAX." lignes — merci de le scinder en plusieurs imports plus petits.");

        $enTetes = $lignes->isNotEmpty() ? array_keys($lignes->first()->toArray()) : [];
        $mapping = $colonnes->detecter($enTetes);

        $cheminTemp = $fichier->store('imports-contacts', 'local');

        $import = ContactImport::create([
            'admin_id' => $request->user()->id,
            'nom_fichier' => $fichier->getClientOriginalName(),
            'total_lignes' => $lignes->count(),
            'statut' => ContactImport::STATUT_EN_COURS,
            'chemin_temporaire' => $cheminTemp,
            'mapping_colonnes' => $mapping,
        ]);

        $apercu = $lignes->take(20)->values()->map(function ($ligne, $index) use ($mapping, $normalisation) {
            $numeroSource = $this->valeurMappee($ligne, $mapping, 'whatsapp') ?? $this->valeurMappee($ligne, $mapping, 'telephone');
            $numero = $normalisation->normaliser($numeroSource);

            return [
                'index' => $index,
                'nom' => $this->valeurMappee($ligne, $mapping, 'nom'),
                'telephone' => $this->valeurMappee($ligne, $mapping, 'telephone'),
                'whatsapp' => $this->valeurMappee($ligne, $mapping, 'whatsapp'),
                'numero_normalise' => $numero['normalise'] ?? null,
                'valide' => $numero['valide'] ?? false,
            ];
        });

        return response()->json([
            'import_id' => $import->id,
            'en_tetes' => $enTetes,
            'mapping_detecte' => $mapping,
            'champs_disponibles' => ContactColonnesExcel::champs(),
            'total_lignes' => $lignes->count(),
            'apercu' => $apercu,
        ]);
    }

    public function confirmer(ImportContactsConfirmerRequest $request, ContactImport $import, NormalisationTelephoneService $normalisation): RedirectResponse
    {
        abort_unless($import->statut === ContactImport::STATUT_EN_COURS, 422, 'Cet import a déjà été traité.');
        abort_unless($import->chemin_temporaire && Storage::disk('local')->exists($import->chemin_temporaire), 422, 'Le fichier temporaire est introuvable — merci de relancer l\'import.');

        $mapping = $request->validated('mapping');
        $strategieMiseAJour = $request->validated('strategie_doublon') === 'mettre_a_jour';
        $lignesIgnorees = collect($request->validated('lignes_ignorees') ?? []);

        $lecteur = new ContactsImportReader;
        Excel::import($lecteur, Storage::disk('local')->path($import->chemin_temporaire));

        // Table de correspondance normalisée -> utilisateur, construite une seule fois
        // (une double boucle contacts x utilisateurs serait trop coûteuse à l'échelle).
        $utilisateursParNumero = $this->indexUtilisateursParNumero($normalisation);

        $importes = 0;
        $maj = 0;
        $ignores = 0;
        $erreurs = [];

        foreach ($lecteur->lignes->values() as $index => $ligne) {
            if ($lignesIgnorees->contains($index)) {
                $ignores++;

                continue;
            }

            $nom = $this->valeurMappee($ligne, $mapping, 'nom');
            $telephone = $this->valeurMappee($ligne, $mapping, 'telephone');
            $whatsapp = $this->valeurMappee($ligne, $mapping, 'whatsapp');
            $numero = $normalisation->normaliser($whatsapp ?: $telephone);

            if (! $numero) {
                $erreurs[] = ['ligne' => $index + 1, 'message' => 'Aucun numéro exploitable sur cette ligne.'];

                continue;
            }

            $existant = $numero['valide']
                ? Contact::where('numero_normalise', $numero['normalise'])->first()
                : null;

            if ($existant && ! $strategieMiseAJour) {
                $ignores++;

                continue;
            }

            $donnees = [
                'nom' => $nom,
                'telephone' => $telephone,
                'whatsapp' => $whatsapp,
                'numero_normalise' => $numero['normalise'],
                'statut_whatsapp' => $numero['valide'] ? Contact::STATUT_WHATSAPP_INCONNU : Contact::STATUT_WHATSAPP_NUMERO_INVALIDE,
                'email' => $this->valeurMappee($ligne, $mapping, 'email'),
                'ville' => $this->valeurMappee($ligne, $mapping, 'ville'),
                'entreprise' => $this->valeurMappee($ligne, $mapping, 'entreprise'),
                'categorie' => $this->valeurMappee($ligne, $mapping, 'categorie'),
                'source' => $this->valeurMappee($ligne, $mapping, 'source') ?: "Import : {$import->nom_fichier}",
                'notes' => $this->valeurMappee($ligne, $mapping, 'notes'),
                'created_by' => $request->user()->id,
                'import_id' => $import->id,
            ];

            if ($numero['valide'] && isset($utilisateursParNumero[$numero['normalise']])) {
                $donnees['utilisateur_id'] = $utilisateursParNumero[$numero['normalise']];
                $donnees['lie_a'] = now();
                $donnees['statut_commercial'] = Contact::STATUT_COMMERCIAL_COMPTE_CREE;
            }

            if ($existant) {
                $existant->update($donnees);
                $maj++;
            } else {
                Contact::create($donnees);
                $importes++;
            }
        }

        Storage::disk('local')->delete($import->chemin_temporaire);

        $import->update([
            'lignes_importees' => $importes,
            'lignes_maj' => $maj,
            'lignes_ignorees' => $ignores,
            'lignes_erreur' => count($erreurs),
            'erreurs' => $erreurs,
            'statut' => ContactImport::STATUT_TERMINE,
            'chemin_temporaire' => null,
        ]);

        return redirect()->route('admin.contacts.import.show', $import->id)
            ->with('flash_success', "Import terminé : {$importes} créés, {$maj} mis à jour, {$ignores} ignorés.");
    }

    public function show(ContactImport $import): Response
    {
        return Inertia::render('Admin/Contacts/Import/Show', [
            'resultat' => $import->load('admin:id,name'),
        ]);
    }

    private function valeurMappee($ligne, array $mapping, string $champ): ?string
    {
        $colonne = $mapping[$champ] ?? null;
        if (! $colonne) {
            return null;
        }
        $valeur = $ligne[$colonne] ?? null;

        return $valeur !== null && trim((string) $valeur) !== '' ? trim((string) $valeur) : null;
    }

    private function indexUtilisateursParNumero(NormalisationTelephoneService $normalisation): array
    {
        $index = [];
        foreach (User::query()->select('id', 'whatsapp', 'telephone')->cursor() as $utilisateur) {
            foreach ([$utilisateur->whatsapp, $utilisateur->telephone] as $numero) {
                $normalise = $normalisation->normaliser($numero)['normalise'] ?? null;
                if ($normalise && ! isset($index[$normalise])) {
                    $index[$normalise] = $utilisateur->id;
                }
            }
        }

        return $index;
    }
}
