<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use App\Models\Contact;
use App\Models\User;
use App\Models\WhatsappContactLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactTest extends TestCase
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

    public function test_admin_with_permission_can_create_a_contact(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir', 'contacts.modifier']);

        $response = $this->actingAs($admin)->post('/admin/contacts', [
            'nom' => 'Jean Dupont',
            'whatsapp' => '690000000',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contacts', ['nom' => 'Jean Dupont', 'numero_normalise' => '+237690000000']);
    }

    public function test_admin_without_permission_cannot_view_contacts_list(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/contacts')->assertStatus(403);
    }

    public function test_regular_user_cannot_access_contacts_at_all(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/contacts')->assertStatus(403);
    }

    public function test_the_main_prospection_filter_returns_only_whatsapp_contacts_without_an_account(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir']);
        Contact::create(['nom' => 'Cible', 'statut_whatsapp' => Contact::STATUT_WHATSAPP_SUR_WHATSAPP, 'utilisateur_id' => null]);
        Contact::create(['nom' => 'Deja inscrit', 'statut_whatsapp' => Contact::STATUT_WHATSAPP_SUR_WHATSAPP, 'utilisateur_id' => User::factory()->create()->id]);
        Contact::create(['nom' => 'Pas whatsapp', 'statut_whatsapp' => Contact::STATUT_WHATSAPP_PAS_SUR_WHATSAPP]);

        $response = $this->actingAs($admin)->get('/admin/contacts?statutWhatsapp=sur_whatsapp&compteLie=0');

        $response->assertInertia(fn ($page) => $page->has('contacts.data', 1)
            ->where('contacts.data.0.nom', 'Cible'));
    }

    public function test_admin_can_manually_update_whatsapp_and_commercial_status(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir', 'contacts.modifier']);
        $contact = Contact::create(['nom' => 'Test']);

        $this->actingAs($admin)->patch("/admin/contacts/{$contact->id}/statut-whatsapp", ['statut_whatsapp' => 'sur_whatsapp'])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/contacts/{$contact->id}/statut-commercial", ['statut_commercial' => 'contacte'])->assertRedirect();

        $contact->refresh();
        $this->assertSame('sur_whatsapp', $contact->statut_whatsapp);
        $this->assertSame('contacte', $contact->statut_commercial);
    }

    public function test_admin_can_relance_a_contact_on_whatsapp_and_a_log_is_created(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir', 'contacts.whatsapp_contacter']);
        $contact = Contact::create(['nom' => 'Marie', 'whatsapp' => '+237691000000']);

        $response = $this->actingAs($admin)->postJson("/admin/contacts/{$contact->id}/whatsapp", ['message' => 'Bonjour Marie']);

        $response->assertOk();
        $this->assertStringStartsWith('https://wa.me/237691000000?text=', $response->json('lien'));
        $this->assertDatabaseHas('whatsapp_contact_logs', ['contact_id' => $contact->id, 'user_id' => null]);
        $this->assertNotNull($contact->fresh()->dernier_contact_a);
    }

    public function test_admin_without_whatsapp_contacter_permission_is_forbidden(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir']);
        $contact = Contact::create(['nom' => 'Marie', 'whatsapp' => '+237691000000']);

        $this->actingAs($admin)->postJson("/admin/contacts/{$contact->id}/whatsapp", ['message' => 'Bonjour'])->assertStatus(403);
    }

    public function test_deleting_a_contact_also_deletes_its_whatsapp_history(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir', 'contacts.supprimer']);
        $contact = Contact::create(['nom' => 'A supprimer', 'whatsapp' => '+237690000000']);
        WhatsappContactLog::create(['contact_id' => $contact->id, 'admin_id' => $admin->id, 'numero_whatsapp' => $contact->whatsapp, 'message' => 'Bonjour', 'ouvert_a' => now()]);

        $this->actingAs($admin)->delete("/admin/contacts/{$contact->id}")->assertRedirect();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseCount('whatsapp_contact_logs', 0);
    }

    public function test_registering_with_a_matching_number_links_the_contact_automatically(): void
    {
        Contact::create(['nom' => 'Futur utilisateur', 'numero_normalise' => '+237690000000', 'statut_commercial' => Contact::STATUT_COMMERCIAL_A_CONTACTER]);

        $user = User::factory()->create(['whatsapp' => '690000000']);

        $contact = Contact::where('numero_normalise', '+237690000000')->first();
        $this->assertSame($user->id, $contact->utilisateur_id);
        $this->assertSame(Contact::STATUT_COMMERCIAL_COMPTE_CREE, $contact->statut_commercial);
        $this->assertNotNull($contact->lie_a);
    }

    public function test_linking_never_downgrades_a_more_advanced_commercial_status(): void
    {
        Contact::create(['nom' => 'Deja converti', 'numero_normalise' => '+237690000001', 'statut_commercial' => Contact::STATUT_COMMERCIAL_CONVERTI]);

        User::factory()->create(['whatsapp' => '690000001']);

        $contact = Contact::where('numero_normalise', '+237690000001')->first();
        $this->assertSame(Contact::STATUT_COMMERCIAL_CONVERTI, $contact->statut_commercial);
    }

    public function test_bulk_commercial_status_update_applies_to_all_selected_contacts(): void
    {
        $admin = $this->creerAdminAvecPermissions(['contacts.voir', 'contacts.modifier']);
        $contact1 = Contact::create(['nom' => 'A']);
        $contact2 = Contact::create(['nom' => 'B']);

        $response = $this->actingAs($admin)->patch('/admin/contacts/statut-commercial-groupe', [
            'ids' => [$contact1->id, $contact2->id],
            'statut_commercial' => 'interesse',
        ]);

        $response->assertRedirect();
        $this->assertSame('interesse', $contact1->fresh()->statut_commercial);
        $this->assertSame('interesse', $contact2->fresh()->statut_commercial);
    }
}
