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

    // ── fromCalcul : variables dérivées de calcul_json (réponse moteur py-port) ──

    /** Réplique la forme réelle de la réponse api_handler d'eor-simulate (:8002). */
    private function calculFixture(): array
    {
        return [
            'scenarios' => [
                'H1' => [
                    'code'                   => 'H1',
                    'age_depart_annees'      => 62,
                    'age_depart_mois'        => 9,
                    'duree_requise'          => 169,
                    'trimestres_acquis_tous' => 159,
                    'decote_pct'             => 12.5, // 10 trimestres × 1,25 %
                ],
            ],
            'enfants' => [
                'nombre'           => 2,
                'sexe_parent'      => 'pere',
                'trim_bonus_total' => 8,
            ],
            'source_calcul' => 'py-port-v1',
        ];
    }

    /** @test */
    public function from_calcul_maps_engine_fields(): void
    {
        $ns = NamespaceBuilder::fromCalcul($this->calculFixture());

        $this->assertSame(8, $ns['trimestres_enfants']);
        $this->assertSame(62.75, $ns['age_legal']);      // 62 ans 9 mois
        $this->assertSame(62, $ns['age_depart_ans']);
        $this->assertSame(10, $ns['nb_trim_decote']);    // 12,5 % / 1,25
        $this->assertSame(10, $ns['manquants_duree']);   // 169 − 159
    }

    /** @test */
    public function from_calcul_is_empty_for_null_or_empty(): void
    {
        $this->assertSame([], NamespaceBuilder::fromCalcul(null));
        $this->assertSame([], NamespaceBuilder::fromCalcul([]));
    }

    /** @test */
    public function from_calcul_omits_missing_sections(): void
    {
        // Pas de section enfants ni de scénario H1 => aucune variable produite
        // => les règles concernées seront "skipped" (jamais un crash).
        $ns = NamespaceBuilder::fromCalcul(['scenarios' => ['H2' => ['decote_pct' => 5.0]]]);
        $this->assertArrayNotHasKey('trimestres_enfants', $ns);
        $this->assertArrayNotHasKey('age_legal', $ns);
        $this->assertArrayNotHasKey('nb_trim_decote', $ns);
        $this->assertArrayNotHasKey('manquants_duree', $ns);
    }

    /** @test */
    public function r001_fires_when_engine_grants_child_quarters_to_a_man(): void
    {
        // Condition réelle du registre. sexe vient du payload, trimestres_enfants du calcul.
        $rule = ['code' => 'R001', 'condition' => 'sexe == "H" and trimestres_enfants > 0',
                 'message' => 'MDA homme', 'niveau' => 'CRITIQUE'];
        $ns = array_merge(
            NamespaceBuilder::fromPayload($this->payload), // sexe = H
            NamespaceBuilder::fromCalcul($this->calculFixture()) // trim_bonus_total = 8
        );
        $res = (new \App\Services\Registre\RuleEvaluator())->evaluate([$rule], $ns);
        $this->assertNotNull($res['arret_critique']);
        $this->assertContains('R001', $res['arret_critique']['codes']);

        // Sans calcul_json : la variable manque => règle skipped, pas d'arrêt.
        $resSkipped = (new \App\Services\Registre\RuleEvaluator())->evaluate(
            [$rule], NamespaceBuilder::fromPayload($this->payload)
        );
        $this->assertNull($resSkipped['arret_critique']);
    }

    /** @test */
    public function r002_fires_on_age_legal_below_62(): void
    {
        $rule = ['code' => 'R002', 'condition' => 'age_legal < 62',
                 'message' => 'âge légal', 'niveau' => 'CRITIQUE'];
        $evaluator = new \App\Services\Registre\RuleEvaluator();

        $calcul = $this->calculFixture();
        $calcul['scenarios']['H1']['age_depart_annees'] = 61;
        $calcul['scenarios']['H1']['age_depart_mois']   = 9; // 61,75 => impossible
        $res = $evaluator->evaluate([$rule], NamespaceBuilder::fromCalcul($calcul));
        $this->assertNotNull($res['arret_critique']);

        // 62 ans 9 mois : conforme, ne se déclenche pas.
        $ok = $evaluator->evaluate([$rule], NamespaceBuilder::fromCalcul($this->calculFixture()));
        $this->assertNull($ok['arret_critique']);
    }

    /** @test */
    public function r006_bouclier_67_fires_on_kreft_case(): void
    {
        // Condition réelle du registre (arbitrage Art. R351-27 CSS).
        $rule = ['code' => 'R006',
                 'condition' => 'nb_trim_decote != min(manquants_duree, (67 - age_depart_ans) * 4)',
                 'message' => 'bouclier 67', 'niveau' => 'CRITIQUE'];
        $evaluator = new \App\Services\Registre\RuleEvaluator();

        // Cas Kreft : 39 trim manquants durée, départ à 64 ans => bouclier = 12 trim.
        // Moteur fautif qui applique 39 : min(39, 12) = 12 ≠ 39 => arrêt critique.
        $kreft = $this->calculFixture();
        $kreft['scenarios']['H1']['age_depart_annees']      = 64;
        $kreft['scenarios']['H1']['age_depart_mois']        = 0;
        $kreft['scenarios']['H1']['duree_requise']          = 169;
        $kreft['scenarios']['H1']['trimestres_acquis_tous'] = 130;      // manquants = 39
        $kreft['scenarios']['H1']['decote_pct']             = 48.75;    // 39 × 1,25 %
        $res = $evaluator->evaluate([$rule], NamespaceBuilder::fromCalcul($kreft));
        $this->assertNotNull($res['arret_critique']);
        $this->assertContains('R006', $res['arret_critique']['codes']);

        // Moteur correct (10 manquants, départ 62 : min(10, 20) = 10 = décote) : silence.
        $ok = $evaluator->evaluate([$rule], NamespaceBuilder::fromCalcul($this->calculFixture()));
        $this->assertNull($ok['arret_critique']);
        $this->assertCount(0, $ok['alertes']);
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
