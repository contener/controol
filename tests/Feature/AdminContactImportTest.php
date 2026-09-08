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
        $response->assertJsonPath('mapping_detecte.whatsapp', 'numero_whatsapp');
        $response->assertJsonPath('mapping_detecte.email', 'e_mail');
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
