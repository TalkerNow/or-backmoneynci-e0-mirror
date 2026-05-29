<?php

namespace Tests\Feature;

use App\Models\DepartureRule;
use App\Models\DepartureRulesHistory;
use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Couvre POST /api/v1/departure-rules/import-pdf : extraction assistée IA d'un
 * barème depuis une circulaire CNAV. Gemini est mocké — on vérifie le mapping
 * sur les 21 lignes, les garde-fous (auth, bornes, valeurs manquantes) et le
 * fait que l'import ne PERSISTE jamais (validation humaine via le PUT existant).
 */
class DepartureRulesImportPdfTest extends TestCase
{
    private function asAdmin(): self
    {
        $user = User::factory()->create(['role' => 'Admin']);
        return $this->actingAs($user, 'api');
    }

    private function asConsultant(): self
    {
        $user = User::factory()->create(['role' => 'Consultant']);
        return $this->actingAs($user, 'api');
    }

    private function fakePdf(): UploadedFile
    {
        return UploadedFile::fake()->create('circulaire_cnav.pdf', 20, 'application/pdf');
    }

    /**
     * Construit la sortie JSON que Gemini renverrait : par défaut chaque ligne
     * renvoie ses valeurs actuelles (aucun changement). $overrides est indexé
     * par sort_order : [1 => ['age_months' => 744, 'trim' => 165], ...].
     */
    private function geminiPayload($rows, array $overrides = []): string
    {
        $payload = [];
        foreach ($rows as $r) {
            $entry = ['sort_order' => $r->sort_order, 'age_months' => $r->age_months, 'trim' => $r->trim];
            if (array_key_exists($r->sort_order, $overrides)) {
                $entry = array_merge($entry, $overrides[$r->sort_order]);
            }
            $payload[] = $entry;
        }
        return json_encode($payload);
    }

    private function mockGemini(string $json): void
    {
        $this->mock(GeminiClient::class, function ($m) use ($json) {
            $m->shouldReceive('generateFromPdf')->andReturn($json);
        });
    }

    private function proposedFor(array $body, int $id): ?array
    {
        return collect($body['proposed'])->firstWhere('id', $id);
    }

    // --- garde-fous d'accès ---

    public function test_import_requires_auth(): void
    {
        $this->postJson('/api/v1/departure-rules/import-pdf')
            ->assertStatus(401);
    }

    public function test_import_forbidden_for_non_admin(): void
    {
        $this->asConsultant()
            ->postJson('/api/v1/departure-rules/import-pdf', ['pdf' => $this->fakePdf()])
            ->assertStatus(403);
    }

    public function test_import_requires_a_pdf_file(): void
    {
        $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', [])
            ->assertStatus(422);
    }

    public function test_import_rejects_non_pdf(): void
    {
        $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', [
                'pdf' => UploadedFile::fake()->create('notice.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422);
    }

    // --- mapping nominal ---

    public function test_import_maps_extraction_onto_existing_rows(): void
    {
        $rows = DepartureRule::ordered()->get();
        $row1 = $rows->firstWhere('sort_order', 1);

        $this->mockGemini($this->geminiPayload($rows, [
            1 => ['age_months' => 744, 'trim' => 165],
        ]));

        $body = $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', ['pdf' => $this->fakePdf()])
            ->assertStatus(200)
            ->json();

        $this->assertCount($rows->count(), $body['proposed']);
        $this->assertEquals(1, $body['changed_count']);
        $this->assertEquals('circulaire_cnav.pdf', $body['source']);

        $p = $this->proposedFor($body, $row1->id);
        $this->assertNotNull($p);
        $this->assertTrue($p['changed']);
        $this->assertTrue($p['found']);
        $this->assertEquals(744, $p['proposed_age_months']);
        $this->assertEquals(165, $p['proposed_trim']);
        $this->assertEquals($row1->age_months, $p['current_age_months']);
    }

    public function test_import_does_not_persist_anything(): void
    {
        $rows         = DepartureRule::ordered()->get();
        $row1         = $rows->firstWhere('sort_order', 1);
        $beforeAge    = $row1->age_months;
        $beforeTrim   = $row1->trim;
        $histBefore   = DepartureRulesHistory::count();

        $this->mockGemini($this->geminiPayload($rows, [
            1 => ['age_months' => 744, 'trim' => 165],
        ]));

        $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', ['pdf' => $this->fakePdf()])
            ->assertStatus(200);

        $fresh = DepartureRule::find($row1->id);
        $this->assertEquals($beforeAge, $fresh->age_months);
        $this->assertEquals($beforeTrim, $fresh->trim);
        $this->assertEquals($histBefore, DepartureRulesHistory::count());
    }

    // --- robustesse extraction ---

    public function test_import_flags_missing_generation_and_keeps_current(): void
    {
        $rows = DepartureRule::ordered()->get();
        $row1 = $rows->firstWhere('sort_order', 1);

        // Génération absente du PDF -> Gemini renvoie null/null.
        $this->mockGemini($this->geminiPayload($rows, [
            1 => ['age_months' => null, 'trim' => null],
        ]));

        $body = $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', ['pdf' => $this->fakePdf()])
            ->assertStatus(200)
            ->json();

        $p = $this->proposedFor($body, $row1->id);
        $this->assertFalse($p['found']);
        $this->assertFalse($p['changed']);
        $this->assertEquals($row1->age_months, $p['proposed_age_months']);
        $this->assertEquals($row1->trim, $p['proposed_trim']);
        $this->assertNotEmpty($body['warnings']);
    }

    public function test_import_ignores_out_of_range_values(): void
    {
        $rows = DepartureRule::ordered()->get();
        $row1 = $rows->firstWhere('sort_order', 1);

        // Âge aberrant (500 mois < borne 720) -> ignoré ; trim valide -> retenu.
        $this->mockGemini($this->geminiPayload($rows, [
            1 => ['age_months' => 500, 'trim' => 165],
        ]));

        $body = $this->asAdmin()
            ->postJson('/api/v1/departure-rules/import-pdf', ['pdf' => $this->fakePdf()])
            ->assertStatus(200)
            ->json();

        $p = $this->proposedFor($body, $row1->id);
        $this->assertFalse($p['found']);                       // âge hors borne => pas "trouvé"
        $this->assertEquals($row1->age_months, $p['proposed_age_months']); // âge actuel conservé
        $this->assertEquals(165, $p['proposed_trim']);         // trim valide appliqué
        $this->assertContains($p['generation'], $body['warnings']);
    }
}
