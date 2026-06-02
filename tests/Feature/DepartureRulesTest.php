<?php

namespace Tests\Feature;

use App\Models\DepartureRule;
use App\Models\DepartureRulesHistory;
use App\Models\User;
use Tests\TestCase;

class DepartureRulesTest extends TestCase
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

    // --- GET /api/v1/departure-rules ---

    public function test_get_returns_21_rows(): void
    {
        $response = $this->asConsultant()
            ->getJson('/api/v1/departure-rules');

        $response->assertStatus(200);
        $this->assertCount(21, $response->json());
    }

    public function test_get_row_has_expected_fields(): void
    {
        $response = $this->asConsultant()
            ->getJson('/api/v1/departure-rules');

        $row = $response->json()[0];
        foreach (['id', 'key_max', 'age_months', 'age_label', 'trim', 'is_default', 'sort_order'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
    }

    public function test_get_default_row_has_null_key_max(): void
    {
        $rows    = $this->asConsultant()
            ->getJson('/api/v1/departure-rules')->json();
        $default = collect($rows)->firstWhere('is_default', true);

        $this->assertNotNull($default, 'No default row found');
        $this->assertNull($default['key_max']);
    }

    public function test_get_age_label_whole_years(): void
    {
        $rows  = $this->asConsultant()
            ->getJson('/api/v1/departure-rules')->json();
        $first = collect($rows)->firstWhere('sort_order', 1);

        $this->assertEquals(720, $first['age_months']);
        $this->assertEquals('60 ans', $first['age_label']);
    }

    public function test_get_age_label_with_months(): void
    {
        $rows = $this->asConsultant()
            ->getJson('/api/v1/departure-rules')->json();
        $row  = collect($rows)->firstWhere('sort_order', 5);

        $this->assertEquals(724, $row['age_months']);
        $this->assertEquals('60 ans et 4 mois', $row['age_label']);
    }

    public function test_get_requires_auth(): void
    {
        $this->getJson('/api/v1/departure-rules')->assertStatus(401);
    }

    // --- PUT /api/v1/departure-rules ---

    public function test_put_updates_age_and_trim(): void
    {
        $rules = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->toArray();

        $rules[0]['age_months'] = 722;
        $rules[0]['trim']       = 161;

        $this->asAdmin()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $updated = DepartureRule::ordered()->first();
        $this->assertEquals(722, $updated->age_months);
        $this->assertEquals(161, $updated->trim);

        // Remettre la valeur originale pour ne pas polluer d'autres tests
        $updated->update(['age_months' => 720, 'trim' => 160]);
    }

    public function test_put_creates_history_snapshot(): void
    {
        $before = DepartureRulesHistory::count();
        $rules  = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->toArray();

        $this->asAdmin()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(200);

        $this->assertEquals($before + 1, DepartureRulesHistory::count());
    }

    public function test_put_rejects_age_months_out_of_range(): void
    {
        $rules              = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->toArray();
        $rules[0]['age_months'] = 700;

        $this->asAdmin()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(422);
    }

    public function test_put_rejects_trim_out_of_range(): void
    {
        $rules           = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->toArray();
        $rules[0]['trim'] = 200;

        $this->asAdmin()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(422);
    }

    public function test_put_rejects_wrong_count(): void
    {
        $rules = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->take(20)->toArray();

        $this->asAdmin()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(422);
    }

    public function test_put_forbidden_for_non_admin(): void
    {
        $rules = DepartureRule::ordered()->get()->map(fn ($r) => [
            'id'         => $r->id,
            'age_months' => $r->age_months,
            'trim'       => $r->trim,
        ])->toArray();

        $this->asConsultant()
            ->putJson('/api/v1/departure-rules', ['rules' => $rules])
            ->assertStatus(403);
    }

    // --- GET /api/v1/departure-rules/history ---

    public function test_history_returns_list(): void
    {
        DepartureRulesHistory::create([
            'rules_json' => DepartureRule::ordered()->get()->toArray(),
            'saved_by'   => null,
        ]);

        $response = $this->asAdmin()
            ->getJson('/api/v1/departure-rules/history');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_history_forbidden_for_non_admin(): void
    {
        $this->asConsultant()
            ->getJson('/api/v1/departure-rules/history')
            ->assertStatus(403);
    }

    // --- POST /api/v1/departure-rules/restore/{id} ---

    public function test_restore_applies_snapshot(): void
    {
        $original = DepartureRule::ordered()->first();

        $snapshot = DepartureRulesHistory::create([
            'rules_json' => DepartureRule::ordered()->get()->map->toArray()->toArray(),
            'saved_by'   => null,
        ]);

        // Modifier la première ligne
        $original->update(['age_months' => 730]);

        // Restaurer
        $this->asAdmin()
            ->postJson("/api/v1/departure-rules/restore/{$snapshot->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals(720, DepartureRule::ordered()->first()->age_months);
    }

    public function test_restore_forbidden_for_non_admin(): void
    {
        $snapshot = DepartureRulesHistory::create([
            'rules_json' => [],
            'saved_by'   => null,
        ]);

        $this->asConsultant()
            ->postJson("/api/v1/departure-rules/restore/{$snapshot->id}")
            ->assertStatus(403);
    }
}
