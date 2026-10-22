<?php

namespace Tests\Feature;

use App\Models\OperationalTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationalTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_agent_can_create_and_view_operational_templates(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($agent);

        // 1. Create a template
        $response = $this->postJson('/api/operational-templates', [
            'title' => 'Template Eskalasi Billing',
            'category' => 'Billing',
            'content' => '<p>Mohon dicek kendala billing pelanggan berikut:</p>',
            'original_filename' => 'template_billing.docx',
            'is_shared' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Template Eskalasi Billing');

        $templateId = $response->json('data.id');

        // 2. Index listing
        $listResponse = $this->getJson('/api/operational-templates');
        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($listResponse->json('data'));

        // 3. Update template
        $updateResponse = $this->putJson("/api/operational-templates/{$templateId}", [
            'title' => 'Template Eskalasi Billing Updated',
            'category' => 'Billing',
            'content' => '<p>Updated content</p>',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Template Eskalasi Billing Updated');

        // 4. Delete template
        $deleteResponse = $this->deleteJson("/api/operational-templates/{$templateId}");
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('operational_templates', ['id' => $templateId]);
    }

    public function test_private_template_not_accessible_by_other_agents(): void
    {
        $agent1 = User::factory()->create();
        $agent1->assignRole('Agent');

        $agent2 = User::factory()->create();
        $agent2->assignRole('Agent');

        $template = OperationalTemplate::create([
            'user_id' => $agent1->id,
            'title' => 'Private Notes',
            'category' => 'Pribadi',
            'content' => '<p>Catatan rahasia</p>',
            'is_shared' => false,
        ]);

        // Agent 2 should NOT see it in index
        Sanctum::actingAs($agent2);
        $listResponse = $this->getJson('/api/operational-templates');
        $this->assertEmpty($listResponse->json('data'));

        // Agent 2 should NOT be able to view details
        $showResponse = $this->getJson("/api/operational-templates/{$template->id}");
        $showResponse->assertStatus(403);
    }

    public function test_each_agent_only_sees_their_own_templates(): void
    {
        $agent1 = User::factory()->create();
        $agent1->assignRole('Agent');

        $agent2 = User::factory()->create();
        $agent2->assignRole('Agent');

        OperationalTemplate::create([
            'user_id' => $agent1->id,
            'title' => 'Agent 1 Personal Template',
            'category' => 'Teknis',
            'content' => '<p>Template milik agent 1</p>',
            'is_shared' => false,
        ]);

        OperationalTemplate::create([
            'user_id' => $agent2->id,
            'title' => 'Agent 2 Personal Template',
            'category' => 'Billing',
            'content' => '<p>Template milik agent 2</p>',
            'is_shared' => false,
        ]);

        // Agent 1 only sees Agent 1 template
        Sanctum::actingAs($agent1);
        $res1 = $this->getJson('/api/operational-templates');
        $res1->assertStatus(200);
        $this->assertCount(1, $res1->json('data'));
        $this->assertEquals('Agent 1 Personal Template', $res1->json('data.0.title'));

        // Agent 2 only sees Agent 2 template
        Sanctum::actingAs($agent2);
        $res2 = $this->getJson('/api/operational-templates');
        $res2->assertStatus(200);
        $this->assertCount(1, $res2->json('data'));
        $this->assertEquals('Agent 2 Personal Template', $res2->json('data.0.title'));
    }

    public function test_pdf_benchmark_template_cannot_be_updated(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($agent);

        $template = OperationalTemplate::create([
            'user_id' => $agent->id,
            'title' => 'SOP Gangguan Indihome',
            'category' => 'SOP',
            'content' => '<p>Konten SOP Baku</p>',
            'original_filename' => 'sop_gangguan.pdf',
            'is_shared' => false,
        ]);

        $response = $this->putJson("/api/operational-templates/{$template->id}", [
            'title' => 'Coba Ubah SOP',
            'category' => 'SOP',
            'content' => '<p>Konten diubah</p>',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Dokumen patokan PDF bersifat baku dan tidak dapat diubah.');
    }

    public function test_pdf_upload_as_file_and_stream(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        Sanctum::actingAs($agent);

        $fakePdf = \Illuminate\Http\UploadedFile::fake()->create('sop_resmi.pdf', 500, 'application/pdf');

        $response = $this->post('/api/operational-templates', [
            'file' => $fakePdf,
            'title' => 'SOP Resmi Call Center',
            'category' => 'SOP',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'SOP Resmi Call Center')
            ->assertJsonPath('data.original_filename', 'sop_resmi.pdf');

        $templateId = $response->json('data.id');
        $filePath = $response->json('data.file_path');

        $this->assertNotNull($filePath);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($filePath);

        // Stream the file
        $streamResponse = $this->get("/api/operational-templates/{$templateId}/file");
        $streamResponse->assertStatus(200);
        $streamResponse->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_non_agent_cannot_access_operational_templates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Sanctum::actingAs($admin);

        // Admin cannot index
        $indexResponse = $this->getJson('/api/operational-templates');
        $indexResponse->assertStatus(403);

        // Admin cannot create
        $createResponse = $this->postJson('/api/operational-templates', [
            'title' => 'Admin Test Template',
            'content' => 'Test content',
        ]);
        $createResponse->assertStatus(403);

        $ts = User::factory()->create();
        $ts->assignRole('TS');

        Sanctum::actingAs($ts);

        // TS cannot index
        $tsResponse = $this->getJson('/api/operational-templates');
        $tsResponse->assertStatus(403);
    }
}
