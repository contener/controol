<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\AdminPermission;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactExportTest extends TestCase
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

    public function test_export_produces_a_downloadable_excel_file(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.exporter']);
        Contact::create(['nom' => 'Jean Dupont', 'whatsapp' => '+237690000000']);

        $response = $this->actingAs($admin)->get('/admin/contacts/export/fichier');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_respects_the_whatsapp_status_filter(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.exporter']);
        Contact::create(['nom' => 'Sur whatsapp', 'statut_whatsapp' => Contact::STATUT_WHATSAPP_SUR_WHATSAPP]);
        Contact::create(['nom' => 'Pas whatsapp', 'statut_whatsapp' => Contact::STATUT_WHATSAPP_PAS_SUR_WHATSAPP]);

        $response = $this->actingAs($admin)->get('/admin/contacts/export/fichier?statutWhatsapp=sur_whatsapp');

        $response->assertOk();
    }

    public function test_export_writes_an_admin_audit_entry(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.exporter']);
        Contact::create(['nom' => 'Jean']);

        $this->actingAs($admin)->get('/admin/contacts/export/fichier');

        $this->assertDatabaseHas('admin_audits', ['admin_id' => $admin->id, 'action' => 'contacts_exportes']);
        $this->assertSame(1, AdminAudit::where('action', 'contacts_exportes')->count());
    }

    public function test_export_requires_the_exporter_permission(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/contacts/export/fichier')->assertStatus(403);
    }
}
