<?php

namespace Tests\Feature;

use App\Models\Gangguan;
use App\Models\GangguanAffectedAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MassOutageMitigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_spike_detection_flags_potential_massal(): void
    {
        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        // Create 3 tickets with same kategori within seconds
        $t1 = Gangguan::create([
            'ticket_number' => uniqid('TK-T1-'),
            'judul' => 'Kendala BOTIKA',
            'deskripsi' => 'Cubicle: A-01\nAgent: Agent 1\nProblem: Error',
            'kategori' => 'BOTIKA',
            'created_by' => $agent->id,
            'status' => 'open',
            'start_time' => now(),
        ]);
        app(\App\Services\SpikeDetectorService::class)->evaluate($t1);

        $t2 = Gangguan::create([
            'ticket_number' => uniqid('TK-T2-'),
            'judul' => 'Kendala BOTIKA 2',
            'deskripsi' => 'Cubicle: A-02\nAgent: Agent 2\nProblem: Error',
            'kategori' => 'BOTIKA',
            'created_by' => $agent->id,
            'status' => 'open',
            'start_time' => now(),
        ]);
        app(\App\Services\SpikeDetectorService::class)->evaluate($t2);

        $t3 = Gangguan::create([
            'ticket_number' => uniqid('TK-T3-'),
            'judul' => 'Kendala BOTIKA 3',
            'deskripsi' => 'Cubicle: A-03\nAgent: Agent 3\nProblem: Error',
            'kategori' => 'BOTIKA',
            'created_by' => $agent->id,
            'status' => 'open',
            'start_time' => now(),
        ]);
        app(\App\Services\SpikeDetectorService::class)->evaluate($t3);

        $this->assertTrue((bool) $t1->fresh()->is_potential_massal);
        $this->assertTrue((bool) $t2->fresh()->is_potential_massal);
        $this->assertTrue((bool) $t3->fresh()->is_potential_massal);
    }

    public function test_agent_can_join_mass_incident_via_me_too(): void
    {
        $agent1 = User::factory()->create();
        $agent1->assignRole('Agent');

        $agent2 = User::factory()->create();
        $agent2->assignRole('Agent');

        $massTicket = Gangguan::create([
            'ticket_number' => uniqid('TK-M1-'),
            'judul' => 'Gangguan Massal SYSCCA',
            'deskripsi' => 'Semua agent tidak bisa login',
            'kategori' => 'SYSCCA',
            'jenis_gangguan' => 'Massal',
            'status' => 'open',
            'created_by' => $agent1->id,
            'jumlah_agent_terdampak' => 1,
            'start_time' => now(),
        ]);

        $response = $this->actingAs($agent2)
            ->postJson("/api/gangguan/{$massTicket->id}/join-incident", [
                'cubicle' => 'B-09',
                'notes' => 'Tidak bisa connect',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('gangguan_affected_agents', [
            'gangguan_id' => $massTicket->id,
            'user_id' => $agent2->id,
            'cubicle_name' => 'B-09',
        ]);

        $this->assertEquals(2, $massTicket->fresh()->jumlah_agent_terdampak);
    }

    public function test_ts_can_bulk_merge_and_cascade_close(): void
    {
        $ts = User::factory()->create();
        $ts->assignRole('TS');

        $agent = User::factory()->create();
        $agent->assignRole('Agent');

        $c1 = Gangguan::create([
            'ticket_number' => uniqid('TK-C1-'),
            'judul' => 'Gangguan 1',
            'deskripsi' => 'Problem 1',
            'kategori' => 'SYSCCA',
            'status' => 'open',
            'created_by' => $agent->id,
            'start_time' => now()->subMinutes(10),
        ]);

        $c2 = Gangguan::create([
            'ticket_number' => uniqid('TK-C2-'),
            'judul' => 'Gangguan 2',
            'deskripsi' => 'Problem 2',
            'kategori' => 'SYSCCA',
            'status' => 'open',
            'created_by' => $agent->id,
            'start_time' => now()->subMinutes(8),
        ]);

        // TS Merges both into a new parent mass ticket
        $mergeRes = $this->actingAs($ts)
            ->postJson('/api/gangguan/bulk-merge', [
                'child_ids' => [$c1->id, $c2->id],
                'new_parent' => [
                    'judul' => 'Gangguan Massal SYSCCA Login',
                    'kategori' => 'SYSCCA',
                    'deskripsi' => 'Konsolidasi laporan SYSCCA',
                ]
            ]);

        $mergeRes->assertStatus(200);
        $parentId = $mergeRes->json('parent.id');
        $this->assertNotNull($parentId);

        $this->assertEquals($parentId, $c1->fresh()->parent_id);
        $this->assertEquals($parentId, $c2->fresh()->parent_id);

        $parent = Gangguan::find($parentId);

        // TS Closes the parent ticket -> should cascade close to all child tickets
        $closeRes = $this->actingAs($ts)
            ->putJson("/api/gangguan/{$parent->id}", [
                'status' => 'closed',
                'penyelesaian_masalah' => 'Restart server SYSCCA',
                'penyebab_permasalahan' => 'High memory usage',
            ]);

        $closeRes->assertStatus(200);

        $this->assertEquals('closed', $parent->fresh()->status);
        $this->assertEquals('closed', $c1->fresh()->status);
        $this->assertEquals('closed', $c2->fresh()->status);
        $this->assertEquals('Restart server SYSCCA', $c1->fresh()->penyelesaian_masalah);
        $this->assertEquals('Restart server SYSCCA', $c2->fresh()->penyelesaian_masalah);
    }
}
