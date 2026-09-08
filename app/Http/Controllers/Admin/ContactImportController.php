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
use App\Services\PreparationFichierCsvService;
use App\Support\ContactColonnesExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function analyser(ImportContactsAnalyserRequest $request, ContactColonnesExcel $colonnes, NormalisationTelephoneService $normalisation, PreparationFichierCsvService $preparation): JsonResponse
    {
        $fichier = $request->file('fichier');

        // Encodage et séparateur ne sont jamais garantis sur un export tiers (Google
        // Contacts notamment) — normalisés une seule fois ici, le fichier stocké est ensuite
        // toujours UTF-8 propre avec un délimiteur connu (mémorisé sur l'import).
        $contenuUtf8 = $preparation->normaliserEnUtf8(file_get_contents($fichier->getRealPath()));
        $delimiteur = $preparation->detecterDelimiteur($contenuUtf8);

        $cheminTemp = 'imports-contacts/'.Str::uuid().'.csv';
        Storage::disk('local')->put($cheminTemp, $contenuUtf8);

        $lecteur = new ContactsImportReader($delimiteur);
        Excel::import($lecteur, Storage::disk('local')->path($cheminTemp));
        $lignes = $lecteur->lignes;

        abort_if($lignes->isEmpty(), 422, 'Le fichier ne contient aucune ligne exploitable.');
        abort_if($lignes->count() > self::LIGNES_MAX, 422, 'Ce fichier contient plus de '.self::LIGNES_MAX." lignes — merci de le scinder en plusieurs imports plus petits.");

        $enTetes = array_keys($lignes->first()->toArray());
        $mappingSimples = $colonnes->detecterSimples($enTetes);
        $colonnesTelephones = $colonnes->colonnesTelephones($enTetes);
        $colonneWhatsapp = $colonnes->colonneWhatsappExplicite($enTetes);
        $colonnesEmails = $colonnes->colonnesEmails($enTetes);

        abort_if(empty($colonnesTelephones) && ! $colonneWhatsapp, 422, "Aucun numéro de téléphone valide n'a été trouvé dans ce fichier.");

        $import = ContactImport::create([
            'admin_id' => $request->user()->id,
            'nom_fichier' => $fichier->getClientOriginalName(),
            'total_lignes' => $lignes->count(),
            'statut' => ContactImport::STATUT_EN_COURS,
            'chemin_temporaire' => $cheminTemp,
            'mapping_colonnes' => $mappingSimples,
            'delimiteur' => $delimiteur,
        ]);

        $apercu = $lignes->take(20)->values()->map(function ($ligne, $index) use ($mappingSimples, $colonnesTelephones, $colonneWhatsapp, $colonnesEmails, $normalisation) {
            $extrait = $this->extraireLigne($ligne, $mappingSimples, $colonnesTelephones, $colonneWhatsapp, $colonnesEmails, $normalisation);

            return [
                'index' => $index,
                'nom' => $extrait['nom'] ?? 'Contact sans nom',
                'telephone' => $extrait['telephone'],
                'whatsapp' => $extrait['whatsapp'],
                'numeros_secondaires' => count($extrait['telephones_secondaires'] ?? []),
                'numero_normalise' => $extrait['numero']['normalise'] ?? null,
                'valide' => $extrait['numero']['valide'] ?? false,
            ];
        });

        return response()->json([
            'import_id' => $import->id,
            'en_tetes' => $enTetes,
            'mapping_detecte' => $mappingSimples,
            'champs_disponibles' => ContactColonnesExcel::champsSimples(),
            'colonnes_telephones_detectees' => array_column($colonnesTelephones, 'valeur'),
            'colonne_whatsapp_detectee' => $colonneWhatsapp,
            'colonnes_emails_detectees' => $colonnesEmails,
            'total_lignes' => $lignes->count(),
            'apercu' => $apercu,
        ]);
    }

    public function confirmer(ImportContactsConfirmerRequest $request, ContactImport $import, NormalisationTelephoneService $normalisation, ContactColonnesExcel $colonnes): RedirectResponse
    {
        abort_unless($import->statut === ContactImport::STATUT_EN_COURS, 422, 'Cet import a déjà été traité.');
        abort_unless($import->chemin_temporaire && Storage::disk('local')->exists($import->chemin_temporaire), 422, "Le fichier temporaire est introuvable — merci de relancer l'import.");

        $mappingSimples = $request->validated('mapping');
        $strategieMiseAJour = $request->validated('strategie_doublon') === 'mettre_a_jour';
        $lignesIgnorees = collect($request->validated('lignes_ignorees') ?? []);

        $lecteur = new ContactsImportReader($import->delimiteur);
        Excel::import($lecteur, Storage::disk('local')->path($import->chemin_temporaire));

        $enTetes = $lecteur->lignes->isNotEmpty() ? array_keys($lecteur->lignes->first()->toArray()) : [];
        $colonnesTelephones = $colonnes->colonnesTelephones($enTetes);
        $colonneWhatsapp = $colonnes->colonneWhatsappExplicite($enTetes);
        $colonnesEmails = $colonnes->colonnesEmails($enTetes);

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

            $extrait = $this->extraireLigne($ligne, $mappingSimples, $colonnesTelephones, $colonneWhatsapp, $colonnesEmails, $normalisation);
            $numero = $extrait['numero'];

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
                'nom' => $extrait['nom'] ?: 'Contact sans nom',
                'prenom' => $extrait['prenom'],
                'nom_famille' => $extrait['nom_famille'],
                'telephone' => $extrait['telephone'],
                'whatsapp' => $extrait['whatsapp'],
                'telephones_secondaires' => $extrait['telephones_secondaires'],
                'numero_normalise' => $numero['normalise'],
                'statut_whatsapp' => $numero['valide'] ? Contact::STATUT_WHATSAPP_INCONNU : Contact::STATUT_WHATSAPP_NUMERO_INVALIDE,
                'email' => $extrait['email'],
                'emails_secondaires' => $extrait['emails_secondaires'],
                'ville' => $extrait['ville'],
                'entreprise' => $extrait['entreprise'],
                'poste' => $extrait['poste'],
                'adresse' => $extrait['adresse'],
                'region' => $extrait['region'],
                'pays' => $extrait['pays'],
                'code_postal' => $extrait['code_postal'],
                'date_anniversaire' => $extrait['date_anniversaire'],
                'categorie' => $extrait['categorie'],
                'source' => $extrait['source'] ?: "Import : {$import->nom_fichier}",
                'notes' => $extrait['notes'],
                'created_by' => $request->user()->id,
                'import_id' => $import->id,
            ];

            if ($numero['valide'] && isset($utilisateursParNumero[$numero['normalise']])) {
                $donnees['utilisateur_id'] = $utilisateursParNumero[$numero['normalise']];
                $donnees['lie_a'] = now();
                $donnees['statut_commercial'] = Contact::STATUT_COMMERCIAL_COMPTE_CREE;
            }

            if ($existant) {
                // Complète le contact existant sans écraser une donnée déjà enregistrée par
                // une valeur vide simplement absente de CE fichier (fusion, pas remplacement).
                $existant->update(array_filter($donnees, fn ($valeur) => $valeur !== null && $valeur !== []));
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

    /**
     * Extraction complète d'une ligne — partagée entre l'aperçu (analyser) et l'import réel
     * (confirmer) pour que l'aperçu ne promette jamais autre chose que ce que la confirmation
     * produit effectivement.
     */
    private function extraireLigne($ligne, array $mappingSimples, array $colonnesTelephones, ?string $colonneWhatsappExplicite, array $colonnesEmails, NormalisationTelephoneService $normalisation): array
    {
        $prenom = $this->valeurMappee($ligne, $mappingSimples, 'prenom');
        $nomFamille = $this->valeurMappee($ligne, $mappingSimples, 'nom_famille');

        $nom = $this->valeurMappee($ligne, $mappingSimples, 'nom');
        if (! $nom) {
            $reconstruit = trim(implode(' ', array_filter([$prenom, $nomFamille])));
            $nom = $reconstruit !== '' ? $reconstruit : null;
        }

        $whatsappBrut = $this->valeurBrute($ligne, $colonneWhatsappExplicite);
        $telephonePrincipal = null;
        $secondaires = [];

        foreach ($colonnesTelephones as $colonne) {
            $valeur = $this->valeurBrute($ligne, $colonne['valeur']);
            if (! $valeur) {
                continue;
            }

            $typeValeur = $colonne['type'] ? mb_strtolower((string) $this->valeurBrute($ligne, $colonne['type'])) : '';
            $estWhatsapp = $typeValeur !== '' && str_contains($typeValeur, 'whatsapp');

            if ($estWhatsapp && ! $whatsappBrut) {
                $whatsappBrut = $valeur;

                continue;
            }

            if (! $telephonePrincipal) {
                $telephonePrincipal = $valeur;
            } else {
                $secondaires[] = $valeur;
            }
        }

        // Un numéro WhatsApp explicite mais aucun autre téléphone détecté : c'est le seul
        // numéro disponible sur ce contact, il sert aussi de numéro principal.
        if (! $telephonePrincipal && $whatsappBrut) {
            $telephonePrincipal = $whatsappBrut;
        }

        $emailPrincipal = null;
        $emailsSecondaires = [];
        foreach ($colonnesEmails as $colonneEmail) {
            $valeur = $this->valeurBrute($ligne, $colonneEmail);
            if (! $valeur) {
                continue;
            }
            if (! $emailPrincipal) {
                $emailPrincipal = $valeur;
            } else {
                $emailsSecondaires[] = $valeur;
            }
        }

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'nom_famille' => $nomFamille,
            'telephone' => $telephonePrincipal,
            'whatsapp' => $whatsappBrut,
            'telephones_secondaires' => $secondaires ?: null,
            'numero' => $normalisation->normaliser($telephonePrincipal),
            'email' => $emailPrincipal,
            'emails_secondaires' => $emailsSecondaires ?: null,
            'ville' => $this->valeurMappee($ligne, $mappingSimples, 'ville'),
            'entreprise' => $this->valeurMappee($ligne, $mappingSimples, 'entreprise'),
            'poste' => $this->valeurMappee($ligne, $mappingSimples, 'poste'),
            'adresse' => $this->valeurMappee($ligne, $mappingSimples, 'adresse'),
            'region' => $this->valeurMappee($ligne, $mappingSimples, 'region'),
            'pays' => $this->valeurMappee($ligne, $mappingSimples, 'pays'),
            'code_postal' => $this->valeurMappee($ligne, $mappingSimples, 'code_postal'),
            'date_anniversaire' => $this->dateEventuelle($this->valeurMappee($ligne, $mappingSimples, 'date_anniversaire')),
            'categorie' => $this->valeurMappee($ligne, $mappingSimples, 'categorie'),
            'source' => $this->valeurMappee($ligne, $mappingSimples, 'source'),
            'notes' => $this->valeurMappee($ligne, $mappingSimples, 'notes'),
        ];
    }

    private function valeurBrute($ligne, ?string $colonne): ?string
    {
        if (! $colonne) {
            return null;
        }
        $valeur = $ligne[$colonne] ?? null;

        return $valeur !== null && trim((string) $valeur) !== '' ? trim((string) $valeur) : null;
    }

    private function valeurMappee($ligne, array $mapping, string $champ): ?string
    {
        return $this->valeurBrute($ligne, $mapping[$champ] ?? null);
    }

    // Un anniversaire Google sans année ("--05-12") ne peut pas être stocké dans une colonne
    // DATE (qui exige une année) — plutôt que d'inventer une année, on l'ignore simplement.
    private function dateEventuelle(?string $valeur): ?string
    {
        if (! $valeur) {
            return null;
        }

        try {
            return Carbon::parse($valeur)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
