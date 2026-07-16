<?php

namespace Tests\Feature;

use App\Models\ConversationArchive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConversationArchiveSourceTest extends TestCase
{
    // Même contrainte que SimulatorChatTest : on réutilise le schéma migré
    // et on rollback après chaque test.
    use DatabaseTransactions;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'summary'  => 'Prospect intéressé par un bilan retraite',
            'messages' => [
                ['role' => 'user', 'content' => 'Bonjour, mon numéro : 06 12 34 56 78'],
                ['role' => 'assistant', 'content' => 'Merci, un consultant vous rappelle.'],
            ],
        ], $overrides);
    }

    public function test_store_without_source_defaults_to_eor()
    {
        $this->postJson('/api/conversation-archives', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('source', 'eor');
    }

    public function test_store_accepts_expert_retraite_source()
    {
        $res = $this->postJson('/api/conversation-archives', $this->validPayload([
            'source' => 'expert-retraite',
        ]));

        $res->assertStatus(201)->assertJsonPath('source', 'expert-retraite');

        $this->getJson('/api/conversation-archives/' . $res->json('id'))
            ->assertStatus(200)
            ->assertJsonPath('source', 'expert-retraite');
    }

    public function test_store_rejects_unknown_source()
    {
        $this->postJson('/api/conversation-archives', $this->validPayload([
            'source' => 'autre-site',
        ]))->assertStatus(422)->assertJsonValidationErrors(['source']);
    }

    public function test_index_exposes_source()
    {
        ConversationArchive::create([
            'summary'  => 'Conversation expert-retraite',
            'messages' => [['role' => 'user', 'content' => 'test']],
            'source'   => 'expert-retraite',
        ]);

        // index() trie par id desc → la ligne créée sort en premier
        $this->getJson('/api/conversation-archives')
            ->assertStatus(200)
            ->assertJsonPath('data.0.source', 'expert-retraite');
    }
}
