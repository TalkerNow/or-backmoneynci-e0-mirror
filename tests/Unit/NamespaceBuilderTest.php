<?php

namespace Tests\Unit;

use App\Services\Registre\NamespaceBuilder;
use Tests\TestCase;

/**
 * Unit tests for NamespaceBuilder::fromPayload() — maps the n8n input payload
 * (built by SimulationRetraiteController::buildN8nPayload) into the documented
 * variable namespace consumed by RuleEvaluator. No DB.
 *
 * MVP : only input-derived variables (calcul_json n'est pas renvoyé par n8n).
 */
class NamespaceBuilderTest extends TestCase
{
    private array $payload = [
        'client' => [
            'sexe'           => 'H',
            'enfants'        => 2,
            'date_naissance' => '1962-05-15',
        ],
        'totaux' => [
            'sam'             => 32000,
            'trimestres_total' => 168,
        ],
    ];

    /** @test */
    public function it_maps_input_payload_fields(): void
    {
        $ns = NamespaceBuilder::fromPayload($this->payload);

        $this->assertSame('H', $ns['sexe']);
        $this->assertSame(2, $ns['nombre_enfants']);
        $this->assertSame(168, $ns['trimestres_total']);
        $this->assertSame(32000, $ns['salaire_annuel_moyen']);
        $this->assertSame('1962-05-15', $ns['date_naissance_client']);
    }

    /** @test */
    public function it_injects_regulatory_constant(): void
    {
        $ns = NamespaceBuilder::fromPayload($this->payload);
        $this->assertSame(20.1877, $ns['PRIX_ACHAT_POINT_AA_2025']);
    }

    /** @test */
    public function it_omits_missing_fields_without_crashing(): void
    {
        $ns = NamespaceBuilder::fromPayload(['client' => [], 'totaux' => []]);
        $this->assertArrayNotHasKey('sexe', $ns);
        $this->assertArrayNotHasKey('trimestres_total', $ns);
        // constant always present
        $this->assertArrayHasKey('PRIX_ACHAT_POINT_AA_2025', $ns);
    }

    /** @test */
    public function it_builds_from_frozen_data_meta_and_totaux(): void
    {
        // Consultation Retraite : la donnée vient de frozen_data.meta + frozen_data.totaux
        $ns = NamespaceBuilder::fromFrozenData(
            ['sexe' => 'F', 'enfants' => 3, 'date_naissance' => '1965-02-10'],
            ['trimestres_total' => 172, 'sam' => 41000]
        );
        $this->assertSame('F', $ns['sexe']);
        $this->assertSame(3, $ns['nombre_enfants']);
        $this->assertSame('1965-02-10', $ns['date_naissance_client']);
        $this->assertSame(172, $ns['trimestres_total']);
        $this->assertSame(41000, $ns['salaire_annuel_moyen']);
        $this->assertSame(20.1877, $ns['PRIX_ACHAT_POINT_AA_2025']);
    }

    /** @test */
    public function from_frozen_data_tolerates_nulls(): void
    {
        $ns = NamespaceBuilder::fromFrozenData(null, null);
        $this->assertArrayNotHasKey('sexe', $ns);
        $this->assertArrayHasKey('PRIX_ACHAT_POINT_AA_2025', $ns);
    }

    /** @test */
    public function it_feeds_rule_evaluator_for_R003(): void
    {
        $ns = NamespaceBuilder::fromPayload([
            'client' => ['sexe' => 'F'],
            'totaux' => ['trimestres_total' => 210],
        ]);
        $res = (new \App\Services\Registre\RuleEvaluator())->evaluate(
            [['code' => 'R003', 'condition' => 'trimestres_total > 200', 'message' => 'm', 'niveau' => 'CRITIQUE']],
            $ns
        );
        $this->assertNotNull($res['arret_critique']);
        $this->assertContains('R003', $res['arret_critique']['codes']);
    }
}
