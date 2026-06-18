<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SimulatorChatSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SimulatorChatTest extends TestCase
{
    // RefreshDatabase indisponible : migrate:fresh est cassé dans cet env (migrations
    // pré-existantes en échec). On réutilise le schéma migré et on rollback après chaque test.
    use DatabaseTransactions;

    private function consultantWithClient(): array
    {
        $consultant = User::factory()->create(['role' => 'Consultant']);
        $client = User::factory()->create(['role' => 'Client', 'parent_id' => $consultant->id]);
        return [$consultant, $client];
    }

    public function test_consultant_creates_session_and_sends_message_with_stub()
    {
        [$consultant, $client] = $this->consultantWithClient();
        $this->actingAs($consultant, 'api');

        $session = $this->postJson('/api/v1/simulator-chat/sessions', [
            'customer_id' => $client->id,
        ])->assertStatus(201)->json();

        $res = $this->postJson("/api/v1/simulator-chat/sessions/{$session['id']}/message", [
            'content' => 'Combien de trimestres a ce client ?',
            'context' => ['carriere' => ['trimestres_cotises' => 152]],
        ])->assertStatus(201)->json();

        $this->assertEquals('user', $res['user_message']['role']);
        $this->assertEquals('assistant', $res['assistant_message']['role']);
        $this->assertStringContainsString('[STUB]', $res['assistant_message']['content']);
    }

    public function test_consultant_cannot_create_session_for_foreign_client()
    {
        [$consultant, ] = $this->consultantWithClient();
        $otherClient = User::factory()->create(['role' => 'Client']); // pas son client
        $this->actingAs($consultant, 'api');

        $this->postJson('/api/v1/simulator-chat/sessions', [
            'customer_id' => $otherClient->id,
        ])->assertStatus(403);
    }

    public function test_consultant_cannot_read_foreign_session()
    {
        [$consultant, $client] = $this->consultantWithClient();
        $otherConsultant = User::factory()->create(['role' => 'Consultant']);
        $session = SimulatorChatSession::create([
            'user_id' => $otherConsultant->id,
            'customer_id' => $client->id,
            'context_page' => 'simulateur_client',
        ]);

        $this->actingAs($consultant, 'api');
        $this->getJson("/api/v1/simulator-chat/sessions/{$session->id}")->assertStatus(403);
    }
}
