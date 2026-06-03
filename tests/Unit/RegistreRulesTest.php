<?php

namespace Tests\Unit;

use App\Services\Registre\RegistreRules;
use Tests\TestCase;

/**
 * Unit tests for RegistreRules::activeRulesFromMarkdown() — selects ACTIVE rules
 * and normalizes them to the payload schema {code, condition, message, niveau}
 * consumed by the n8n Python evaluator. No DB.
 */
class RegistreRulesTest extends TestCase
{
    private string $md = <<<MD
### R001 | Trimestres enfants attribués à un homme
**Condition Python** : `sexe == "H" and trimestres_enfants > 0`
**Message d'erreur** : "Impossible d'attribuer des trimestres pour enfants à un homme."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Bloque le calcul.

---

### R050 | Avertissement SAM élevé
**Condition Python** : `salaire_annuel_moyen > 200000`
**Message d'erreur** : "SAM élevé, vérifier plafonnement."
**Niveau** : 🟠 AVERTISSEMENT
**Statut** : ✅ ACTIF

**Impact** : Avertissement seulement.

---

### R099 | Règle désactivée
**Condition Python** : `age_legal < 62`
**Message d'erreur** : "Age legal incoherent."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ❌ INACTIF

**Impact** : Désactivée.
MD;

    /** @test */
    public function it_selects_only_active_rules_normalized(): void
    {
        $rules = (new RegistreRules())->activeRulesFromMarkdown($this->md);

        $codes = array_column($rules, 'code');
        $this->assertContains('R001', $codes);
        $this->assertContains('R050', $codes);
        $this->assertNotContains('R099', $codes); // inactive excluded
    }

    /** @test */
    public function it_normalizes_to_payload_schema(): void
    {
        $rules = (new RegistreRules())->activeRulesFromMarkdown($this->md);
        $byCode = collect($rules)->keyBy('code');

        $r001 = $byCode['R001'];
        $this->assertSame(['code', 'condition', 'message', 'niveau'], array_keys($r001));
        $this->assertSame('sexe == "H" and trimestres_enfants > 0', $r001['condition']);
        $this->assertSame('CRITIQUE', $r001['niveau']);
        $this->assertStringContainsString('Impossible', $r001['message']);

        $this->assertSame('AVERTISSEMENT', $byCode['R050']['niveau']);
    }

    /** @test */
    public function it_loads_active_r010_tranche_c_rule_from_the_real_registry_doc(): void
    {
        // Teste le VRAI texte de la règle dans le doc source (attrape une faute
        // de frappe dans la condition, le niveau, etc.).
        $md = file_get_contents(base_path('docs/REGISTRE_ERREURS_COHERENCE.md'));
        $this->assertNotFalse($md);

        $rules  = (new RegistreRules())->activeRulesFromMarkdown($md);
        $byCode = collect($rules)->keyBy('code');

        $this->assertArrayHasKey('R010', $byCode->all());
        $this->assertSame('salaire_brut_max > 4 * PASS_2025', $byCode['R010']['condition']);
        $this->assertSame('AVERTISSEMENT', $byCode['R010']['niveau']);

        // Bout-en-bout : la vraie règle se déclenche pour un haut revenu, sans bloquer.
        $ns = \App\Services\Registre\NamespaceBuilder::fromPayload([
            'carriere' => [['annee' => 2025, 'revenu_brut' => 208245]],
        ]);
        $res   = (new \App\Services\Registre\RuleEvaluator())->evaluate($rules, $ns);
        $codes = array_column($res['alertes'], 'code');
        $this->assertContains('R010', $codes);
        $this->assertNull($res['arret_critique']); // avertissement, jamais bloquant
    }
}
