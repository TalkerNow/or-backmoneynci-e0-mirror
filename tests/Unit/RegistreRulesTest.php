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
}
