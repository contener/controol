<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminContactImportTest extends TestCase
{
    use RefreshDatabase;

    private function creerAdminAvecPermissions(array $permissions): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ($permissions as $permission) {
            AdminPermission::create(['user_id' => $admin->id, 'permission' => $permission]);
        }

        return $admin;
    }

    private function creerFichierCsv(array $lignes): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'contacts_test_').'.csv';
        $handle = fopen($chemin, 'w');
        foreach ($lignes as $ligne) {
            fputcsv($handle, $ligne);
        }
        fclose($handle);

        return new UploadedFile($chemin, 'contacts.csv', 'text/csv', null, true);
    }

    public function test_column_variants_are_correctly_detected(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Full Name', 'Numéro WhatsApp', 'E-mail'],
            ['Jean Dupont', '690000000', 'jean@example.com'],
        ]);

        $response = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier]);

        $response->assertOk();
        $response->assertJsonPath('mapping_detecte.nom', 'full_name');
        $response->assertJsonPath('colonne_whatsapp_detectee', 'numero_whatsapp');
        $response->assertJsonPath('colonnes_emails_detectees', ['e_mail']);
    }

    public function test_google_contacts_csv_with_numbered_phone_columns_is_imported_successfully(): void
    {
        // Régression du bug rapporté : les en-têtes réelles d'un export Google Contacts
        // (Phone 1 - Value, E-mail 1 - Value...) ne correspondaient à aucun alias connu —
        // chaque ligne finissait en erreur et zéro contact n'était jamais créé.
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['First Name', 'Last Name', 'Phone 1 - Type', 'Phone 1 - Value', 'E-mail 1 - Value'],
            ['Jean', 'Dupont', 'Mobile', '+237 690 000 000', 'jean@example.com'],
        ]);

        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();
        $this->assertSame(1, $analyse['total_lignes']);
        $this->assertTrue($analyse['apercu'][0]['valide']);

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseHas('contacts', [
            'nom' => 'Jean Dupont',
            'telephone' => '+237 690 000 000',
            'numero_normalise' => '+237690000000',
            'email' => 'jean@example.com',
        ]);
    }

    public function test_full_name_is_reconstructed_from_first_and_last_name_columns(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['First Name', 'Last Name', 'Phone 1 - Value'],
            ['Marie', 'Ngo', '691000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseHas('contacts', ['nom' => 'Marie Ngo', 'prenom' => 'Marie', 'nom_famille' => 'Ngo']);
    }

    public function test_semicolon_delimiter_is_automatically_detected(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $chemin = tempnam(sys_get_temp_dir(), 'contacts_test_').'.csv';
        file_put_contents($chemin, "Nom;Telephone\nJean Dupont;+237690000000\n");
        $fichier = new UploadedFile($chemin, 'contacts.csv', 'text/csv', null, true);

        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->assertSame(1, $analyse['total_lignes']);
        $this->assertSame('Jean Dupont', $analyse['apercu'][0]['nom']);
    }

    public function test_utf8_bom_is_stripped_from_the_first_column_name(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $chemin = tempnam(sys_get_temp_dir(), 'contacts_test_').'.csv';
        file_put_contents($chemin, "\xEF\xBB\xBFNom,Telephone\nJean Dupont,+237690000000\n");
        $fichier = new UploadedFile($chemin, 'contacts.csv', 'text/csv', null, true);

        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->assertArrayHasKey('nom', $analyse['mapping_detecte']);
        $this->assertSame('nom', $analyse['mapping_detecte']['nom']);
    }

    public function test_windows_1252_encoding_is_converted_and_accents_are_preserved(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $contenu = mb_convert_encoding("Nom,Telephone\nFrançois Éric,+237690000000\n", 'Windows-1252', 'UTF-8');
        $chemin = tempnam(sys_get_temp_dir(), 'contacts_test_').'.csv';
        file_put_contents($chemin, $contenu);
        $fichier = new UploadedFile($chemin, 'contacts.csv', 'text/csv', null, true);

        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->assertSame('François Éric', $analyse['apercu'][0]['nom']);
    }

    public function test_multiple_phone_numbers_are_preserved_as_secondary_phones(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'Phone 1 - Value', 'Phone 2 - Value'],
            ['Jean', '690000000', '691000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $contact = Contact::where('nom', 'Jean')->first();
        $this->assertSame('690000000', $contact->telephone);
        $this->assertSame(['691000000'], $contact->telephones_secondaires);
    }

    public function test_multiple_emails_are_preserved_as_secondary_emails(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'Phone 1 - Value', 'E-mail 1 - Value', 'E-mail 2 - Value'],
            ['Jean', '690000000', 'jean@pro.com', 'jean@perso.com'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $contact = Contact::where('nom', 'Jean')->first();
        $this->assertSame('jean@pro.com', $contact->email);
        $this->assertSame(['jean@perso.com'], $contact->emails_secondaires);
    }

    public function test_phone_column_explicitly_typed_whatsapp_is_used_as_the_whatsapp_number(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'Phone 1 - Type', 'Phone 1 - Value', 'Phone 2 - Type', 'Phone 2 - Value'],
            ['Jean', 'Home', '690000000', 'WhatsApp', '691000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $contact = Contact::where('nom', 'Jean')->first();
        $this->assertSame('690000000', $contact->telephone);
        $this->assertSame('691000000', $contact->whatsapp);
    }

    public function test_row_with_a_phone_but_no_name_is_imported_with_a_placeholder_name(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Phone 1 - Value'],
            ['690000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseHas('contacts', ['nom' => 'Contact sans nom', 'telephone' => '690000000']);
    }

    public function test_enrichment_fields_are_imported_when_present(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'Phone 1 - Value', 'Organization Title', 'Address 1 - Country', 'Address 1 - Postal Code'],
            ['Jean', '690000000', 'Directeur', 'Cameroun', '00237'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseHas('contacts', [
            'nom' => 'Jean',
            'poste' => 'Directeur',
            'pays' => 'Cameroun',
            'code_postal' => '00237',
        ]);
    }

    public function test_updating_an_existing_contact_never_erases_data_missing_from_the_new_file(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        Contact::create(['nom' => 'Jean Dupont', 'numero_normalise' => '+237690000000', 'email' => 'jean@example.com', 'entreprise' => 'ABC SARL']);

        $fichier = $this->creerFichierCsv([
            ['Nom', 'Phone 1 - Value'],
            ['Jean Dupont', '690000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseHas('contacts', ['nom' => 'Jean Dupont', 'email' => 'jean@example.com', 'entreprise' => 'ABC SARL']);
    }

    public function test_analyser_creates_an_import_record_without_creating_any_contact_yet(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Jean', '690000000'],
        ]);

        $response = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier]);

        $response->assertOk();
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseHas('contact_imports', ['statut' => ContactImport::STATUT_EN_COURS, 'total_lignes' => 1]);
    }

    public function test_confirmer_creates_contacts_with_normalized_phone_numbers(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Jean Dupont', '690 000 000'],
        ]);

        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $response = $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['nom' => 'Jean Dupont', 'numero_normalise' => '+237690000000']);
        $this->assertSame(ContactImport::STATUT_TERMINE, ContactImport::find($analyse['import_id'])->statut);
    }

    public function test_duplicate_is_ignored_when_strategy_is_ignorer(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        Contact::create(['nom' => 'Ancien nom', 'numero_normalise' => '+237690000000']);

        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Nouveau nom', '690000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'ignorer',
        ]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('contacts', ['nom' => 'Ancien nom']);
    }

    public function test_duplicate_is_updated_when_strategy_is_mettre_a_jour(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        Contact::create(['nom' => 'Ancien nom', 'numero_normalise' => '+237690000000']);

        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Nouveau nom', '690000000'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('contacts', ['nom' => 'Nouveau nom', 'numero_normalise' => '+237690000000']);
    }

    public function test_row_without_any_usable_number_is_recorded_as_an_error(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Sans numero', ''],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
        ]);

        $import = ContactImport::find($analyse['import_id']);
        $this->assertSame(1, $import->lignes_erreur);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_row_marked_as_ignored_by_the_admin_is_skipped(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.importer']);
        $fichier = $this->creerFichierCsv([
            ['Nom', 'WhatsApp'],
            ['Premier', '690000001'],
            ['Deuxieme', '690000002'],
        ]);
        $analyse = $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->json();

        $this->actingAs($admin)->post("/admin/contacts/import/{$analyse['import_id']}/confirmer", [
            'mapping' => $analyse['mapping_detecte'],
            'strategie_doublon' => 'mettre_a_jour',
            'lignes_ignorees' => [0],
        ]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('contacts', ['nom' => 'Deuxieme']);
    }

    public function test_import_permission_is_required_on_both_analyser_and_confirmer_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $fichier = $this->creerFichierCsv([['Nom', 'WhatsApp'], ['Jean', '690000000']]);

        $this->actingAs($admin)->post('/admin/contacts/import/analyser', ['fichier' => $fichier])->assertStatus(403);

        $import = ContactImport::create(['admin_id' => User::factory()->create(['role' => User::ROLE_ADMIN])->id, 'nom_fichier' => 'x.csv', 'statut' => ContactImport::STATUT_EN_COURS]);
        $this->actingAs($admin)->post("/admin/contacts/import/{$import->id}/confirmer", ['mapping' => [], 'strategie_doublon' => 'ignorer'])->assertStatus(403);
    }
}
