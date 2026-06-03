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

    /** @test */
    public function it_exposes_salaire_brut_max_from_carriere_revenu_brut(): void
    {
        // Tranche C detection needs the UNCAPPED raw salary (revenu_brut), not the
        // SAM (capped at 1 PASS). We expose the career max so a rule can compare it to 4 PASS.
        $ns = NamespaceBuilder::fromPayload([
            'client'   => [],
            'totaux'   => [],
            'carriere' => [
                ['annee' => 2010, 'revenu_brut' => 90000],
                ['annee' => 2025, 'revenu_brut' => 208245],
                ['annee' => 2024, 'revenu_brut' => 150000],
                ['annee' => 1999, 'revenu_brut' => 0],
            ],
        ]);
        $this->assertSame(208245, $ns['salaire_brut_max']);
    }

    /** @test */
    public function it_injects_pass_2025_constant(): void
    {
        $ns = NamespaceBuilder::fromPayload(['client' => [], 'totaux' => []]);
        $this->assertSame(47100, $ns['PASS_2025']);
    }

    /** @test */
    public function it_omits_salaire_brut_max_when_carriere_absent_or_has_no_salary(): void
    {
        $this->assertArrayNotHasKey('salaire_brut_max', NamespaceBuilder::fromPayload(['client' => [], 'totaux' => []]));
        $this->assertArrayNotHasKey('salaire_brut_max', NamespaceBuilder::fromPayload(['client' => [], 'totaux' => [], 'carriere' => []]));
        $this->assertArrayNotHasKey('salaire_brut_max', NamespaceBuilder::fromPayload(['carriere' => [['annee' => 2000]]]));
    }

    /** @test */
    public function tranche_c_warning_rule_fires_for_high_earner_only(): void
    {
        // R010 : haut revenu cadre (un salaire annuel > 4 PASS) => AVERTISSEMENT (non bloquant).
        $rule = [
            'code'      => 'R010',
            'condition' => 'salaire_brut_max > 4 * PASS_2025',
            'message'   => 'tranche C',
            'niveau'    => 'AVERTISSEMENT',
        ];
        $evaluator = new \App\Services\Registre\RuleEvaluator();

        $high = NamespaceBuilder::fromPayload(['carriere' => [['annee' => 2025, 'revenu_brut' => 208245]]]);
        $resHigh = $evaluator->evaluate([$rule], $high);
        $this->assertCount(1, $resHigh['alertes']);
        $this->assertSame('R010', $resHigh['alertes'][0]['code']);
        $this->assertSame('AVERTISSEMENT', $resHigh['alertes'][0]['niveau']);
        $this->assertNull($resHigh['arret_critique']); // avertissement, jamais bloquant

        $low = NamespaceBuilder::fromPayload(['carriere' => [['annee' => 2025, 'revenu_brut' => 40000]]]);
        $resLow = $evaluator->evaluate([$rule], $low);
        $this->assertCount(0, $resLow['alertes']);
    }
}
