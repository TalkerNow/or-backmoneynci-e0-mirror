# SKILL : Pension de Réversion

## Métadonnées

```yaml
skill_id: SKILL_REVERSION_v1
version: 1.0
date: 2026-04-06
base_reglementaire:
  - Articles L.353-1 à L.353-7 du Code de la Sécurité Sociale
  - Circulaire CNAV 2023-22 du 17/07/2023
  - Accord AGIRC-ARRCO du 30/10/2015
  - Décret n°2023-436 du 03/06/2023
script_python: null  # à créer en Phase 2
regles_json: reversion_regles.json
```

## Description

Calculer et analyser les droits à pension de réversion du conjoint survivant (ou ex-conjoint) après le décès d'un assuré. Couvre le régime général (CNAV), l'AGIRC-ARRCO, l'IRCANTEC et le RCI/RCO.

## Déclencheurs

- Questions sur la retraite du conjoint survivant
- Décès d'un assuré retraité ou actif
- Mots-clés : "réversion", "conjoint survivant", "veuf", "veuve", "après son décès", "pension de veuf"
- Simulation anticipée ("si mon mari décède avant moi...")

---

## ⚠️ POINT CRITIQUE — CONDITIONS PAR RÉGIME

La réversion n'est PAS uniforme. Les conditions varient fortement selon le régime :

| Critère | CNAV (base) | AGIRC-ARRCO | IRCANTEC | RCI/RCO |
|---|---|---|---|---|
| Lien requis | Mariage uniquement | Mariage uniquement | Mariage uniquement | Mariage uniquement |
| PACS | ❌ Exclu | ❌ Exclu | ❌ Exclu | ❌ Exclu |
| Concubinage | ❌ Exclu | ❌ Exclu | ❌ Exclu | ❌ Exclu |
| Âge minimum | 55 ans | Aucun | Aucun | Aucun |
| Condition ressources | ✅ Oui | ❌ Non | ❌ Non | ❌ Non |
| Taux | 54% | 60% | 50% | 54% |
| Remariage | Supprime le droit | Supprime le droit | Supprime le droit | Supprime le droit |

---

## 📐 RÈGLES DE CALCUL PAR RÉGIME

### 1. CNAV — Régime Général (base)

**Taux** : 54% de la pension de retraite de base du défunt (ou de la pension qu'il aurait perçue)

**Conditions cumulatives** :
1. Avoir été marié avec le défunt (durée de mariage non requise depuis 2003)
2. Avoir au moins **55 ans** au moment de la demande
3. Ne pas dépasser le **plafond de ressources annuel**

**Plafonds de ressources 2025** :

| Situation | Plafond annuel |
|---|---|
| Personne seule | **20 551,60 €** |
| Vie en couple (remariage ou concubinage) | **32 882,56 €** |

**⚠️ Ressources prises en compte** : tous revenus du bénéficiaire sauf prestations familiales, ASI, allocation veuvage, RSA, et la réversion elle-même.

**Calcul pratique** :
```
Pension réversion CNAV = pension_base_défunt × 54%
Si pension_réversion + ressources_bénéficiaire > plafond → écrêtement
Montant final = plafond - ressources_bénéficiaire (si dépassement)
```

**Minimum de réversion 2025** : **311,56 €/mois** (si pension calculée < minimum)

**Majoration 3 enfants** : +10% si le bénéficiaire a élevé au moins 3 enfants

### 2. AGIRC-ARRCO (complémentaire salariés privé)

**Taux** : 60% des points du défunt

**Conditions** :
1. Avoir été marié (pas de condition d'âge, pas de condition de ressources)
2. Ne pas s'être remarié

**Calcul** :
```
Points_réversion = points_défunt × 60%
Pension_mensuelle = points_réversion × valeur_point_AGIRC_ARRCO (1,4386 €)
```

**Cas de divorce** : si plusieurs ex-conjoints, les points sont répartis au prorata de la durée de chaque mariage.

**Majoration enfants** : +10% si 3 enfants ou plus élevés (plafonné à la pension brute)

### 3. IRCANTEC (contractuels publics)

**Taux** : 50% des droits acquis par le défunt

**Conditions** : mariage, pas de condition d'âge ni de ressources

**Calcul** :
```
Points_réversion = points_défunt × 50%
Pension_mensuelle = points_réversion × valeur_point_IRCANTEC (0,56357 €)
```

### 4. RCI/RCO (indépendants)

**Taux** : 54% de la pension du défunt

**Conditions** : mariage, pas de condition d'âge ni de ressources pour le RCO

---

## 📋 DONNÉES NÉCESSAIRES POUR LE CALCUL

### Du défunt
- Date de naissance
- Date de décès
- Statut au moment du décès (retraité ou actif)
- Pension de retraite base perçue (si retraité) OU estimation (si actif)
- Points AGIRC-ARRCO acquis
- Points IRCANTEC acquis (si applicable)
- Points RCI/RCO acquis (si applicable)
- Nombre d'enfants élevés

### Du bénéficiaire
- Date de naissance
- Date du mariage avec le défunt
- Date de divorce (si applicable) — pour calcul prorata
- Ressources annuelles propres (pour test plafond CNAV)
- Situation maritale actuelle (veuf/veuve, remarié, en concubinage)
- Nombre d'enfants élevés (pour majoration)

---

## 🚨 CONTRÔLES OBLIGATOIRES

| Code | Règle | Gravité |
|---|---|---|
| REV_C01 | Vérifier que le lien est un mariage — PACS et concubinage exclus de tous les régimes | CRITIQUE |
| REV_C02 | Vérifier l'âge du bénéficiaire ≥ 55 ans pour la CNAV uniquement | CRITIQUE |
| REV_C03 | Vérifier absence de remariage (supprime le droit dans tous les régimes) | CRITIQUE |
| REV_C04 | Calculer le test de ressources CNAV avant de valider le montant | CRITIQUE |
| REV_C05 | Si plusieurs ex-conjoints AGIRC-ARRCO → répartition prorata durée mariage | IMPORTANT |
| REV_C06 | Appliquer le minimum de réversion CNAV si pension calculée < 311,56 €/mois | IMPORTANT |
| REV_C07 | Vérifier la majoration 3 enfants (applicable CNAV et AGIRC-ARRCO) | IMPORTANT |

**⚠️ ARRÊT CRITIQUE** si REV_C01 ou REV_C03 → pas de droit à réversion, stop et expliquer.

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_REVERSION_v1",
  "beneficiaire_eligible": true,
  "motif_ineligibilite": null,
  "detail": {
    "cnav": {
      "eligible": true,
      "conditions_age_ok": true,
      "test_ressources": {
        "ressources_beneficiaire": 0,
        "plafond_applicable": 20551.60,
        "depassement": false
      },
      "pension_brute_mensuelle": 0.00,
      "ecrêtement_applique": false,
      "pension_finale_mensuelle": 0.00,
      "minimum_applique": false,
      "majoration_enfants": false
    },
    "agirc_arrco": {
      "eligible": true,
      "points_reversion": 0,
      "pension_mensuelle": 0.00,
      "majoration_enfants": false,
      "ex_conjoints_multiples": false
    },
    "ircantec": {
      "eligible": false,
      "pension_mensuelle": 0.00
    },
    "rci_rco": {
      "eligible": false,
      "pension_mensuelle": 0.00
    }
  },
  "total_mensuel_brut": 0.00,
  "alertes": [],
  "arret_critique": false
}
```

---

## 📊 SIMULATION TEMPORELLE — QUAND RÉCLAMER LA RÉVERSION CNAV ?

### Le problème

La réversion CNAV est soumise au test de ressources. Si le bénéficiaire travaille encore à 55 ans, ses revenus professionnels peuvent dépasser le plafond → réversion réduite ou nulle. Attendre la retraite fait chuter les revenus → réversion pleine. Mais attendre = perdre des années de versements.

**Ce calcul est obligatoire** dès que le bénéficiaire a moins de 65 ans et perçoit des revenus professionnels.

### Calcul du break-even

```
Scénario A — Réclamer à 55 ans :
  montant_mensuel_A = max(0, reversion_brute - écrêtement(ressources_actuelles))
  cumul_A(âge) = montant_mensuel_A × 12 × (âge - 55)

Scénario B — Attendre la retraite (âge X) :
  montant_mensuel_B = reversion_brute (sans écrêtement si revenus < plafond à la retraite)
  cumul_B(âge) = montant_mensuel_B × 12 × (âge - X)

Break-even = âge où cumul_A(âge) = cumul_B(âge)
```

### Règle de recommandation

| Situation | Recommandation |
|---|---|
| Bénéficiaire > plafond ressources ET break-even < espérance de vie | Attendre la retraite |
| Bénéficiaire > plafond ressources ET break-even > espérance de vie | Réclamer à 55 ans (même réduit) |
| Bénéficiaire < plafond ressources dès 55 ans | Réclamer immédiatement |
| Bénéficiaire en situation précaire (revenus très faibles) | Réclamer immédiatement + vérifier allocation veuvage |

### AGIRC-ARRCO : toujours réclamer immédiatement

Pas de condition d'âge ni de ressources → la réversion AGIRC-ARRCO doit être réclamée **indépendamment et immédiatement**, quelle que soit la stratégie retenue pour la CNAV.

Le conseil stratégique complet est donc :
1. Réclamer AGIRC-ARRCO tout de suite (toujours)
2. Simuler le break-even CNAV avant de décider quand réclamer la base

### Données supplémentaires nécessaires pour la simulation

- Revenus professionnels actuels du bénéficiaire
- Âge de départ à la retraite prévu du bénéficiaire
- Revenus prévisionnels à la retraite du bénéficiaire (estimation pension propre)
- Espérance de vie de référence (table INSEE par sexe et âge)

---

## ⚠️ CAS PARTICULIERS À SIGNALER

- **Divorce + remariage du défunt** : ex-conjoint divorcé peut avoir des droits AGIRC-ARRCO (prorata), mais pas la CNAV si le défunt s'est remarié
- **Conjoint survivant qui se remarie** : perd tous les droits à réversion (tous régimes)
- **Conjoint survivant en concubinage** : conserve le droit CNAV si non remarié, mais le plafond ressources inclut les revenus du concubin
- **Défunt décédé avant la retraite** : pension de réversion calculée sur la pension qu'il aurait perçue (estimation nécessaire via skill_cnav)
- **Allocation veuvage** : versée avant 55 ans si revenus insuffisants — distinct de la réversion

---

## 📚 Sources réglementaires

- Articles L.353-1 à L.353-7 du Code de la Sécurité Sociale
- Circulaire CNAV 2023-22
- `circulaires/VALEURS_REGLEMENTAIRES_2025.md` (plafonds et minimums)
- `circulaires/REGIMES-COMPLEMENTAIRE-AGIRC_ARRCO.md`
