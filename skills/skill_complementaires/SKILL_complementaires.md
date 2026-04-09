# SKILL : Calcul des Pensions Complémentaires

## Métadonnées

```yaml
skill_id: SKILL_COMPLEMENTAIRES_v1
version: 1.0
date: 2025-11-01
base_reglementaire:
  - Circulaire CNAV 2024-36
  - Circulaire CNAV 2025-14
  - Accord AGIRC-ARRCO du 30/10/2015
  - Règle abattement AGIRC-ARRCO avril 2024
script_python: calcul_complementaires.py
regles_json: complementaires_regles.json
```

## Description

Calculer les pensions des régimes complémentaires pour tout client ayant cotisé à AGIRC-ARRCO (salarié privé), IRCANTEC (contractuel public), ou RCI/RCO (indépendant). Toujours utilisé en complément du skill CNAV.

## Déclencheurs

- Calcul de pension complète (toujours déclenché après SKILL_calcul_cnav)
- Présence de points AGIRC-ARRCO, IRCANTEC, RCI ou RCO dans le relevé
- Questions sur la retraite complémentaire
- Client salarié du privé, contractuel public, ou indépendant

---

## 💰 VALEURS DES POINTS 2025

| Régime | Valeur du point | Date d'effet |
|---|---|---|
| AGIRC-ARRCO | **1,4386 €** | 1er novembre 2024 |
| IRCANTEC | **0,56357 €** | 1er janvier 2025 |
| RCI | **1,335 €** | 2025 |
| RCO | **1,200 €** | 2025 |

**⚠️ Ces valeurs changent chaque année.** Vérifier `circulaires/VALEURS_REGLEMENTAIRES_2025.md` avant tout calcul.

---

## 📐 RÈGLES DE CALCUL PAR RÉGIME

### 1. AGIRC-ARRCO (salariés du privé)

**Formule de base** : `pension_annuelle = nombre_points × 1,4386`

**Règle abattement AVRIL 2024** — Critique :

| Condition | Abattement |
|---|---|
| Taux plein atteint | **Aucun** |
| Départ à l'âge légal (même sans taux plein) | **Aucun** |
| Départ à 67 ans | **Aucun** |
| Inaptitude / Invalidité / Pénibilité | **Aucun** |
| Carrière longue AVANT taux plein | **Abattement selon tableau** |

**Tableau d'abattement (carrière longue avant taux plein uniquement)** :

| Trimestres manquants | Coefficient |
|---|---|
| 1 à 4 | 0,99 |
| 5 à 8 | 0,98 |
| 9 à 12 | 0,97 |
| 13 à 16 | 0,96 |
| 17 à 20 | 0,95 |
| > 20 | 0,95 (minimum) |

### 2. IRCANTEC (contractuels fonction publique)

**Formule de base** : `pension_annuelle = nombre_points × 0,56357`

**Coefficients de minoration par trimestres manquants** :

| Trimestres manquants | Coefficient |
|---|---|
| 0 | 1,00 |
| 1 | 0,99 |
| 5 | 0,95 |
| 10 | 0,90 |
| 15 | 0,8425 |
| 20 | 0,78 |
| > 20 | 0,78 (minimum) |

*(Table complète dans `calcul_complementaires.py` — COEFFICIENTS_MINORATION_IRCANTEC)*

### 3. RCI et RCO (indépendants)

**Formule RCI** : `pension_rci = points_rci × 1,335 × coefficient_abattement`
**Formule RCO** : `pension_rco = points_rco × 1,200 × coefficient_abattement`

**⚠️ RÈGLE CRITIQUE RCI/RCO** : **PAS DE SURCOTE** pour ces régimes, contrairement au régime de base.

**Abattements RCI/RCO (si taux plein non atteint)** :

| Trimestres manquants | Coefficient |
|---|---|
| 0 | 1,000 |
| 1 | 0,9875 |
| 5 | 0,9375 |
| 10 | 0,875 |
| 15 | 0,8125 |
| 20 | 0,75 (maximum = -25%) |
| > 20 | 0,75 (minimum) |

---

## 🔢 CALCUL CONSOLIDÉ (TOUS RÉGIMES)

Utiliser la fonction `calculer_tous_complementaires()` dans `calcul_complementaires.py`.

Paramètres d'entrée :
```python
{
  "points_agirc_arrco": float,      # Si salarié privé
  "points_ircantec": float,          # Si contractuel public
  "points_rci": float,               # Si indépendant
  "points_rco": float,               # Si indépendant
  "depart_avant_age_legal": bool,    # True = carrière longue
  "taux_plein_atteint": bool,
  "trimestres_manquants_taux_plein": int,
  "situation_speciale": bool         # True = inaptitude/invalidité
}
```

---

## 🚨 CONTRÔLES OBLIGATOIRES

- **COMP_C01** — Ne jamais appliquer abattement AGIRC-ARRCO si taux plein atteint
- **COMP_C02** — Ne jamais appliquer surcote au RCI/RCO
- **COMP_C03** — Vérifier la valeur du point en cours (changer chaque novembre/janvier)
- **COMP_C04** — Ne pas cumuler AGIRC-ARRCO + IRCANTEC sur la même période (régimes exclusifs)
- **COMP_C05** — Si points = 0 mais durée de cotisation > 0 → alerte, données manquantes
- **COMP_C06** — Poly-pensionnés (plusieurs régimes sur des périodes différentes) : calculer séparément sans double compte

**⚠️ ARRÊT CRITIQUE** si COMP_C04 ou COMP_C05 détecté → ne pas produire d'estimation, signaler à Jeff.

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_COMPLEMENTAIRES_v1",
  "pension_totale_mensuelle": 0.00,
  "pension_totale_annuelle": 0.00,
  "detail": {
    "agirc_arrco": {
      "points": 0,
      "valeur_point": 1.4386,
      "coefficient": 1.0,
      "abattement_applique": false,
      "pension_mensuelle": 0.00
    },
    "ircantec": {
      "points": 0,
      "valeur_point": 0.56357,
      "coefficient": 1.0,
      "pension_mensuelle": 0.00
    },
    "rci_rco": {
      "points_rci": 0,
      "points_rco": 0,
      "coefficient_abattement": 1.0,
      "pension_mensuelle": 0.00
    }
  },
  "alertes": [],
  "arret_critique": false
}
```

---

## 📚 Sources réglementaires

- Accord AGIRC-ARRCO du 30/10/2015 (modifié 01/04/2024)
- `circulaires/REGIMES-COMPLEMENTAIRE-AGIRC_ARRCO.md`
- `circulaires/circulaire_rci_2025.md`
- `circulaires/VALEURS_REGLEMENTAIRES_2025.md`
