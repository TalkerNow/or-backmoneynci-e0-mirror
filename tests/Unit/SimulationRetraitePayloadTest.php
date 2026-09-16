<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\SimulationRetraiteController;
use App\Models\FrozenData;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Unit tests for SimulationRetraiteController::buildN8nPayload().
 *
 * The method is a pure data builder with no DB or HTTP I/O, but Eloquent's
 * datetime cast (locked_at) requires a connection resolver, so we boot the
 * app via Tests\TestCase. No DB query is executed.
 */
class SimulationRetraitePayloadTest extends TestCase
{
    /** @test */
    public function it_builds_a_v2_payload_with_all_blocks_populated(): void
    {
        $frozen = $this->makeFrozen([
            'carriere' => [
                ['annee' => 1981, 'regime' => 'cnav', 'revenu_brut' => 8200, 'salaire_revalo' => 28100, 'trimestres' => 4],
                ['annee' => 1982, 'regime' => 'cnav', 'revenu_brut' => 9000, 'salaire_revalo' => 30000, 'trimestres' => 4],
            ],
            'totaux' => [
                'trimestres_par_regime' => ['cnav' => 140, 'cipav' => 28],
                'trimestres_total'      => 172,
            ],
            'cipav'             => ['points' => 1234],
            'carpimko'          => ['points' => 0],
            'regimes_points'    => ['CARMF' => ['base' => 100]],
            'scenarios_choisis' => [
                [
                    'dispositif_id' => 'racl',
                    'label'         => 'Carrière longue (RACL)',
                    'skill_code'    => 'RACL',
                    'last_calc'     => ['montant' => 1850],
                    'chosen_at'     => '2026-05-06T11:00:00Z',
                ],
            ],
            'dates_retenues' => [
                ['type' => 'taux_plein', 'label' => 'Taux plein', 'date' => '2024-09-01'],
            ],
            'alertes' => [['niveau' => 'warning', 'code' => 'TRIM_GAP']],
        ]);
        $frozen->id        = 87;
        $frozen->source    = 'RIS_CNAV_2026';
        $frozen->locked_at = Carbon::parse('2026-04-22T09:11:00Z');
        $frozen->locked_by = 42;

        $resolvedMeta = [
            'nom'            => 'DUPONT',
            'prenom'         => 'Jean',
            'date_naissance' => '1962-03-15',
            'sexe'           => 'H',
            'nir'            => '1620375XXXXXX12',
            'enfants'        => 2,
        ];

        $calculsSkills = [
            ['skill_id' => 'racl',    'result_json' => ['html' => '...'], 'calcul_json' => null, 'alertes_json' => [], 'statut' => 'brouillon', 'updated_at' => '2026-05-06T10:21:00Z'],
            ['skill_id' => 'chomage', 'result_json' => null,              'calcul_json' => ['days' => 365], 'alertes_json' => [], 'statut' => 'brouillon', 'updated_at' => '2026-05-06T10:00:00Z'],
        ];

        $payload = SimulationRetraiteController::buildN8nPayload(
            $frozen,
            1405,
            3500.0,
            $resolvedMeta,
            $calculsSkills
        );

        // Top-level structure
        $this->assertSame('2', $payload['version']);
        $this->assertSame(1405, $payload['client_id']);
        $this->assertSame(3500.0, $payload['revenu_souhaite']);
        $this->assertNotEmpty($payload['generated_at']);

        // client block
        $this->assertSame('DUPONT', $payload['client']['nom']);
        $this->assertSame('1962-03-15', $payload['client']['date_naissance']);
        $this->assertSame(2, $payload['client']['enfants']);

        // frozen block
        $this->assertSame(87, $payload['frozen']['id']);
        $this->assertSame('RIS_CNAV_2026', $payload['frozen']['source']);
        $this->assertTrue($payload['frozen']['locked']);
        $this->assertSame(42, $payload['frozen']['locked_by']);

        // carriere — passed through as-is
        $this->assertCount(2, $payload['carriere']);
        $this->assertSame(1981, $payload['carriere'][0]['annee']);

        // totaux — enriched with sam (avg of top 25 salaire_revalo) and trimestres_cotises_rg
        $this->assertSame(29050, $payload['totaux']['sam']); // (28100 + 30000) / 2
        $this->assertSame(140, $payload['totaux']['trimestres_cotises_rg']);
        $this->assertSame(172, $payload['totaux']['trimestres_total']); // preserved

        // regimes block
        $this->assertSame(['points' => 1234], $payload['regimes']['cipav']);
        $this->assertSame(['points' => 0], $payload['regimes']['carpimko']);
        $this->assertSame(['CARMF' => ['base' => 100]], $payload['regimes']['regimes_points']);

        // scenarios_retenus + dates_retenues — passed through
        $this->assertCount(1, $payload['scenarios_retenus']);
        $this->assertSame('Carrière longue (RACL)', $payload['scenarios_retenus'][0]['label']);
        $this->assertCount(1, $payload['dates_retenues']);
        $this->assertSame('2024-09-01', $payload['dates_retenues'][0]['date']);

        // calculs_skills — both reports present
        $this->assertCount(2, $payload['calculs_skills']);
        $skillIds = array_column($payload['calculs_skills'], 'skill_id');
        $this->assertContains('racl', $skillIds);
        $this->assertContains('chomage', $skillIds);

        // alertes
        $this->assertCount(1, $payload['alertes']);
    }

    /** @test */
    public function it_respects_a_frontend_provided_sam(): void
    {
        // Le frontend gèle désormais totaux.sam (computeSamCnav — le chiffre affiché
        // et validé). enrichTotaux ne doit PAS l'écraser par son propre recalcul.
        $frozen = $this->makeFrozen([
            'carriere' => [
                ['annee' => 2020, 'salaire_revalo' => 50000],
                ['annee' => 2021, 'salaire_revalo' => 52000],
            ],
            'totaux' => ['sam' => 33500],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 7, 0.0, [], []);
        $this->assertSame(33500, $payload['totaux']['sam']);
    }

    /** @test */
    public function sam_fallback_ignores_empty_grid_years(): void
    {
        // La grille gèle 65 lignes dont la plupart à 0 € : une carrière de 10 années
        // cotisées doit donner la moyenne de CES 10 années, pas une moyenne diluée /25.
        $carriere = [];
        for ($i = 0; $i < 10; $i++) {
            $carriere[] = ['annee' => 2010 + $i, 'salaire_revalo' => 30000];
        }
        for ($i = 0; $i < 30; $i++) {
            $carriere[] = ['annee' => 1980 + $i, 'salaire_revalo' => 0];
        }
        $frozen = $this->makeFrozen(['carriere' => $carriere, 'totaux' => []]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 7, 0.0, [], []);
        $this->assertSame(30000, $payload['totaux']['sam']); // pas 12000 (= 300000/25)
    }

    /** @test */
    public function sam_fallback_keeps_only_the_top_25_years(): void
    {
        $carriere = [];
        for ($i = 0; $i < 25; $i++) {
            $carriere[] = ['annee' => 2001 + $i, 'salaire_revalo' => 40000];
        }
        for ($i = 0; $i < 5; $i++) {
            $carriere[] = ['annee' => 1990 + $i, 'salaire_revalo' => 10000];
        }
        $frozen = $this->makeFrozen(['carriere' => $carriere, 'totaux' => []]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 7, 0.0, [], []);
        $this->assertSame(40000, $payload['totaux']['sam']); // les 5 années faibles exclues
    }

    /** @test */
    public function it_falls_back_singular_to_array_when_only_legacy_columns_are_set(): void
    {
        $frozen = $this->makeFrozen([
            'carriere'          => [],
            'totaux'            => [],
            'scenarios_choisis' => null,
            'dates_retenues'    => null,
            'scenario_choisi'   => ['dispositif_id' => 'legacy_racl', 'label' => 'Legacy'],
            'date_retenue'      => ['type' => 'age_legal', 'date' => '2025-01-01'],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 1, 0.0, [], []);

        $this->assertCount(1, $payload['scenarios_retenus']);
        $this->assertSame('legacy_racl', $payload['scenarios_retenus'][0]['dispositif_id']);

        $this->assertCount(1, $payload['dates_retenues']);
        $this->assertSame('2025-01-01', $payload['dates_retenues'][0]['date']);
    }

    /** @test */
    public function it_returns_empty_arrays_when_no_scenarios_or_dates_are_set(): void
    {
        $frozen = $this->makeFrozen([
            'carriere' => [],
            'totaux'   => [],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 1, 0.0, [], []);

        $this->assertSame([], $payload['scenarios_retenus']);
        $this->assertSame([], $payload['dates_retenues']);
        $this->assertSame([], $payload['calculs_skills']);
        $this->assertSame([], $payload['alertes']);
    }

    /** @test */
    public function it_prefers_array_over_singular_when_both_are_set(): void
    {
        $frozen = $this->makeFrozen([
            'scenarios_choisis' => [['dispositif_id' => 'new_racl']],
            'scenario_choisi'   => ['dispositif_id' => 'old_racl'],
            'dates_retenues'    => [['date' => '2030-01-01']],
            'date_retenue'      => ['date' => '1999-01-01'],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 1, 0.0, [], []);

        $this->assertCount(1, $payload['scenarios_retenus']);
        $this->assertSame('new_racl', $payload['scenarios_retenus'][0]['dispositif_id']);
        $this->assertSame('2030-01-01', $payload['dates_retenues'][0]['date']);
    }

    /** @test */
    public function it_fills_unknown_meta_fields_with_null(): void
    {
        $frozen = $this->makeFrozen([
            'carriere' => [],
            'totaux'   => [],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload(
            $frozen,
            42,
            0.0,
            ['date_naissance' => '1980-06-15'],
            []
        );

        $this->assertSame(42, $payload['client']['user_id']);
        $this->assertSame('1980-06-15', $payload['client']['date_naissance']);
        $this->assertNull($payload['client']['nom']);
        $this->assertNull($payload['client']['prenom']);
        $this->assertNull($payload['client']['nir']);
        $this->assertNull($payload['client']['enfants']);
    }

    /** @test */
    public function trimestres_cotises_rg_falls_back_when_cnav_missing(): void
    {
        $frozen = $this->makeFrozen([
            'carriere' => [],
            'totaux'   => [
                'trimestres_par_regime' => ['msa' => 50],
                'trimestres_cotises'    => 88,
            ],
        ]);

        $payload = SimulationRetraiteController::buildN8nPayload($frozen, 1, 0.0, [], []);

        // par_regime.cnav absent → falls back to trimestres_cotises
        $this->assertSame(88, $payload['totaux']['trimestres_cotises_rg']);
    }

    /**
     * Build a FrozenData instance with mass-assignable attributes, no DB.
     */
    private function makeFrozen(array $attrs): FrozenData
    {
        $frozen = new FrozenData($attrs);
        $frozen->id = $attrs['id'] ?? 1;
        return $frozen;
    }
}
