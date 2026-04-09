# SKILL : Autocontrôle Zéro Erreur

## Métadonnées

```yaml
skill_id: SKILL_VALIDATION_AUTOCONTROLE_v1
version: 1.1
date: 2026-04-06
origine: Créé suite à l'erreur — attribution de trimestres maternité à un homme
architecture: Fail-safe by design (inspiré aviation, médical, finance)
script_python: validation_autocontrole.py
```

## Description

Système de validation à 3 niveaux (Gates) qui BLOQUE la production si les données obligatoires sont manquantes, incohérentes ou insuffisantes. S'exécute AVANT tout calcul de pension. Impossible d'avancer sans validation complète de chaque Gate.

## Déclencheurs

- **Toujours** — Ce skill est déclenché en Gate #1 avant tout calcul
- Tout workflow de consultation retraite
- Réception d'un nouveau dossier client
- Relance d'une consultation après correction

---

## 🚦 ARCHITECTURE EN 3 GATES

### GATE #1 — Données Obligatoires

**Blocage immédiat si l'une de ces données est manquante** :

| Donnée | Champ | Note |
|---|---|---|
| Identité | nom, prénom | |
| Sexe | H ou F | Critique pour maternité |
| Date de naissance | JJ/MM/AAAA | |
| Statut marital | célibataire/marié/pacsé/divorcé/veuf | |
| Nombre d'enfants | entier ≥ 0 | |
| Enfants nés avant 2010 | oui/non | Pour majorations |
| Date début carrière | JJ/MM/AAAA | |
| Relevé de carrière disponible | oui/non | |
| Type de demande | estimation / rachat / carrière_longue | |

**Résultat GATE #1** :
- ✅ Toutes présentes → passer à GATE #2
- ❌ Une manquante → BLOQUER, lister les champs manquants, ne pas continuer

### GATE #2 — Cohérence des Données

**Contrôles de cohérence obligatoires** :

| Code | Règle | Gravité |
|---|---|---|
| AC_C01 | Trimestres maternité ≠ 0 UNIQUEMENT si sexe = F | CRITIQUE |
| AC_C02 | Trimestres service national ≤ 4 | CRITIQUE |
| AC_C03 | Trimestres chômage ≤ 4 | CRITIQUE |
| AC_C04 | Trimestres maladie+AT ≤ 4 (global) | CRITIQUE |
| AC_C05 | Trimestres invalidité ≤ 2 | CRITIQUE |
| AC_C06 | Total validés ≥ total cotisés stricts | CRITIQUE |
| AC_C07 | Âge début carrière ≥ 14 ans (date_naissance + 14 ans ≤ date_début) | CRITIQUE |
| AC_C08 | Trimestres totaux ≤ (âge actuel - âge début) × 4 | CRITIQUE |
| AC_C09 | Si enfants > 0 ET nés avant 2010 → vérifier majorations CNAV | IMPORTANT |
| AC_C10 | Si périodes étrangères → pays identifié | IMPORTANT |

**Résultat GATE #2** :
- ✅ Toutes cohérences OK → passer à GATE #3
- ❌ Contrôle CRITIQUE échoué → BLOQUER immédiatement, logger dans REGISTRE_ERREURS
- ⚠️ Contrôle IMPORTANT échoué → signaler, demander confirmation, ne pas bloquer

### GATE #3 — Suffisance pour le Calcul Demandé

Vérifications selon le type de demande :

**Si demande = "estimation"** :
- Date de naissance ✅
- Trimestres validés actuels ✅
- Si en activité → âge de départ prévu ✅
- Points complémentaires (si disponibles) → sinon noter "estimation partielle"

**Si demande = "rachat"** :
- Relevé de carrière complet ✅
- Nombre de trimestres à racheter ✅
- Âge de départ cible ✅

**Si demande = "carrière_longue"** :
- Relevé de carrière complet ✅
- Trimestres avant 16/18/20/21 ans documentés ✅

---

## ⛔ COMPORTEMENT EN CAS D'ÉCHEC

1. **STOPPER** le process immédiatement
2. **NE PAS** produire d'estimation partielle
3. **NE PAS** interpoler les données manquantes
4. Retourner un JSON avec `arret_critique: true`
5. Lister précisément les données manquantes / incohérences détectées
6. Logger dans `REGISTRE_ERREURS_COHERENCE.md` (si erreur critique)

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_VALIDATION_AUTOCONTROLE_v1",
  "gate_1_ok": false,
  "gate_2_ok": false,
  "gate_3_ok": false,
  "validation_globale": false,
  "donnees_manquantes": [
    "sexe",
    "date_naissance"
  ],
  "incoherences": [
    {
      "code": "AC_C01",
      "gravite": "CRITIQUE",
      "message": "Trimestres maternité attribués à un homme"
    }
  ],
  "alertes": [],
  "arret_critique": true,
  "action_requise": "Compléter les données manquantes avant tout calcul"
}
```

---

## 📚 Origine et contexte

Ce skill a été créé suite à une erreur réelle en consultation : attribution de trimestres maternité à un homme lors d'un calcul RACL. Le système GATE #2 / contrôle AC_C01 aurait bloqué cette erreur avant qu'elle n'atteigne le calcul.

Architecture inspirée des systèmes critiques (aviation : checklist obligatoire, médical : protocole de validation, finance : contrôle 4 yeux).
