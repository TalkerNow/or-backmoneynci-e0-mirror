<?php

namespace Tests\Unit;

use App\Services\Registre\RuleEvaluator;
use Tests\TestCase;

/**
 * Unit tests for the SAFE rule evaluator (Gate #2 enforcement, PHP side).
 *
 * evaluate(rules, namespace) returns:
 *   ['alertes' => [{code,message,niveau}], 'arret_critique' => {raison,codes}|null, 'skipped' => [{code,reason}]]
 * Conditions are strings evaluated by a restricted recursive-descent evaluator
 * (NEVER eval()): comparisons, and/or/not, + - * / %, min/max/abs/round/len,
 * variable access + indexation only. Mirrors the validated Python semantics.
 */
class RuleEvaluatorTest extends TestCase
{
    private function rule(string $code, string $cond, string $niveau = 'CRITIQUE', string $msg = 'msg'): array
    {
        return ['code' => $code, 'condition' => $cond, 'message' => $msg, 'niveau' => $niveau];
    }

    /** @test */
    public function critical_rule_fires_and_sets_arret_critique(): void
    {
        $res = (new RuleEvaluator())->evaluate([$this->rule('R002', 'age_legal < 62')], ['age_legal' => 61]);
        $this->assertContains('R002', array_column($res['alertes'], 'code'));
        $this->assertNotNull($res['arret_critique']);
        $this->assertContains('R002', $res['arret_critique']['codes']);
    }

    /** @test */
    public function coherent_value_does_not_fire(): void
    {
        $res = (new RuleEvaluator())->evaluate([$this->rule('R002', 'age_legal < 62')], ['age_legal' => 64]);
        $this->assertSame([], $res['alertes']);
        $this->assertNull($res['arret_critique']);
    }

    /** @test */
    public function boolean_and_man_fires_woman_does_not(): void
    {
        $rule = $this->rule('R001', 'sexe == "H" and trimestres_enfants > 0');
        $fire = (new RuleEvaluator())->evaluate([$rule], ['sexe' => 'H', 'trimestres_enfants' => 8]);
        $this->assertNotNull($fire['arret_critique']);
        $nofire = (new RuleEvaluator())->evaluate([$rule], ['sexe' => 'F', 'trimestres_enfants' => 8]);
        $this->assertNull($nofire['arret_critique']);
    }

    /** @test */
    public function warning_never_blocks(): void
    {
        $res = (new RuleEvaluator())->evaluate(
            [$this->rule('R050', 'salaire_annuel_moyen > 200000', 'AVERTISSEMENT')],
            ['salaire_annuel_moyen' => 250000]
        );
        $this->assertContains('R050', array_column($res['alertes'], 'code'));
        $this->assertNull($res['arret_critique']);
    }

    /** @test */
    public function multiple_rules_aggregate(): void
    {
        $res = (new RuleEvaluator())->evaluate([
            $this->rule('R002', 'age_legal < 62'),
            $this->rule('R003', 'trimestres_total > 200'),
            $this->rule('R050', 'salaire_annuel_moyen > 200000', 'AVERTISSEMENT'),
        ], ['age_legal' => 61, 'trimestres_total' => 210, 'salaire_annuel_moyen' => 250000]);

        $codes = array_column($res['alertes'], 'code');
        sort($codes);
        $this->assertSame(['R002', 'R003', 'R050'], $codes);
        $critCodes = $res['arret_critique']['codes'];
        sort($critCodes);
        $this->assertSame(['R002', 'R003'], $critCodes);
    }

    /** @test */
    public function missing_variable_is_skipped_not_crash(): void
    {
        $res = (new RuleEvaluator())->evaluate([$this->rule('R002', 'age_legal < 62')], []);
        $this->assertSame([], $res['alertes']);
        $this->assertNull($res['arret_critique']);
        $this->assertContains('R002', array_column($res['skipped'], 'code'));
    }

    /** @test */
    public function malicious_condition_is_rejected_not_executed(): void
    {
        // PHP function call / arbitrary code must NOT be evaluated.
        $res = (new RuleEvaluator())->evaluate(
            [$this->rule('RX', 'system("echo pwned")')],
            []
        );
        $this->assertNull($res['arret_critique']);
        $this->assertContains('RX', array_column($res['skipped'], 'code'));
    }

    /** @test */
    public function min_function_allowed_for_bouclier_67(): void
    {
        $rule = $this->rule('R006', 'nb_trim_decote != min(manquants_duree, (67 - age_depart_ans) * 4)');
        // age_depart=62 -> (67-62)*4=20 ; manquants=39 -> min=20 ; nb=39 != 20 -> fire
        $fire = (new RuleEvaluator())->evaluate([$rule], ['nb_trim_decote' => 39, 'manquants_duree' => 39, 'age_depart_ans' => 62]);
        $this->assertNotNull($fire['arret_critique']);
        $nofire = (new RuleEvaluator())->evaluate([$rule], ['nb_trim_decote' => 20, 'manquants_duree' => 39, 'age_depart_ans' => 62]);
        $this->assertNull($nofire['arret_critique']);
    }

    /** @test */
    public function empty_rules_returns_empty(): void
    {
        $res = (new RuleEvaluator())->evaluate([], ['age_legal' => 61]);
        $this->assertSame([], $res['alertes']);
        $this->assertNull($res['arret_critique']);
    }

    /** @test */
    public function placeholder_condition_is_skipped(): void
    {
        $res = (new RuleEvaluator())->evaluate([$this->rule('R009', '# à définir')], []);
        $this->assertContains('R009', array_column($res['skipped'], 'code'));
        $this->assertNull($res['arret_critique']);
    }
}
