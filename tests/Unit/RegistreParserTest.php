<?php

namespace Tests\Unit;

use App\Services\Registre\RegistreParser;
use Tests\TestCase;

/**
 * Unit tests for RegistreParser — pure markdown parsing of the
 * REGISTRE_ERREURS_COHERENCE prompt. No DB, no HTTP.
 */
class RegistreParserTest extends TestCase
{
    private string $md = <<<MD
# REGISTRE DES ERREURS DE COHÉRENCE

## STATISTIQUES
- **Total erreurs capturées** : 3
- **Règles actives** : 2
- **Règles archivées** : 1
- **Dernière mise à jour** : 06/04/2026

## RÈGLES ACTIVES (GATE #2)

### R001 | Trimestres enfants attribués à un homme
**Date d'ajout** : 06/11/2025
**Cas origine** : M. Dupont
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système initial
**Erreur détectée** : Attribution de 8 trimestres pour enfants à un homme
**Condition Python** : `sexe == "H" and trimestres_enfants > 0`
**Message d'erreur** : "Impossible d'attribuer des trimestres pour enfants à un homme."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Bloque tout calcul attribuant des majorations enfants à un homme.

---

### R002 | Âge légal inférieur à 62 ans
**Date d'ajout** : 06/11/2025
**Condition Python** : `age_legal < 62`
**Message d'erreur** : "Âge légal inférieur à 62 ans impossible."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Empêche les estimations avec un âge légal incohérent.

---

### R050 | Règle désactivée d'exemple
**Condition Python** : `salaire_annuel_moyen > 200000`
**Message d'erreur** : "SAM élevé, vérifier plafonnement."
**Niveau** : 🟠 AVERTISSEMENT
**Statut** : ❌ INACTIF

**Impact** : Avertissement seulement.

## 📋 TEMPLATE POUR AJOUTER UNE NOUVELLE RÈGLE
MD;

    /** @test */
    public function it_parses_all_rules_with_their_fields(): void
    {
        $rules = (new RegistreParser())->parseAll($this->md);

        $this->assertCount(3, $rules);

        $codes = array_column($rules, 'code');
        $this->assertSame(['R001', 'R002', 'R050'], $codes);

        $r001 = $rules[0];
        $this->assertSame('Trimestres enfants attribués à un homme', $r001['title']);
        $this->assertSame('sexe == "H" and trimestres_enfants > 0', $r001['condition_python']);
        $this->assertStringContainsString('CRITIQUE', $r001['niveau']);
        $this->assertStringContainsString('✅', $r001['statut']);
    }

    /** @test */
    public function it_splits_active_from_archived_by_statut(): void
    {
        $parsed = (new RegistreParser())->parse($this->md);

        $this->assertCount(2, $parsed['active_rules']);
        $this->assertCount(1, $parsed['archived_rules']);
        $this->assertSame('R050', $parsed['archived_rules'][0]['code']);
        $this->assertSame(2, $parsed['stats']['active_rules_count']);
        $this->assertSame(1, $parsed['stats']['archived_rules_count']);
    }
}
