# SKILL : Analyse de Relevé de Carrière Retraite

## Métadonnées

```yaml
skill_id: SKILL_ANALYSE_RELEVE_v2
version: 2.0
date: 2025-11-10
base_reglementaire:
  - Circulaire CNAV 2024
  - Décret n°2023-436 du 03/06/2023
  - Loi n°2023-270 du 14/04/2023
script_python: calcul_analyse_carriere.py
regles_json: analyse_releve_regles.json
```

## Description

Analyser un relevé de carrière retraite (RIS) pour extraire les données clés, calculer les âges de départ possibles, détecter les anomalies et estimer les pensions. Point d'entrée principal de toute consultation.

## Déclencheurs

- Réception d'un relevé de carrière (RIS)
- Questions sur l'âge de départ, les trimestres validés, la pension estimée
- Mots-clés : "relevé de carrière", "analyse carrière", "RIS", "trimestres validés", "PROMPT 1"
- Demande d'analyse de situation retraite

---

## ⚠️ RÈGLE CRITIQUE : ÉTAPE 0 OBLIGATOIRE

**TOUJOURS commencer par demander :**

> "Êtes-vous actuellement en activité professionnelle ? Si oui, jusqu'à quel âge envisagez-vous de travailler ?"

**Pourquoi c'est critique** : Un relevé de carrière est une photographie à un instant T. Sans cette information, l'analyse est incomplète et les résultats erronés.

**Calcul projection si en activité** :
```
trimestres_futurs = (âge_départ_prévu - âge_actuel) × 4
total = trimestres_actuels + trimestres_futurs
```

Toujours expliciter : "Hypothèse : En poursuivant votre activité jusqu'à [âge], vous validerez environ [X] trimestres supplémentaires, portant votre total à [Y] trimestres."

---

## 📋 MÉTHODOLOGIE EN 6 ÉTAPES

### Étape 1 — Extraction des données

Extraire obligatoirement :

- Date de naissance (via NSS ou mention explicite)
- Trimestres validés tous régimes confondus
- Trimestres cotisés stricts
- Trimestres réputés cotisés (service national, chômage, maladie, maternité, invalidité, AVPF)
- Régimes de cotisation (CNAV, AGIRC-ARRCO, IRCANTEC, RCI/RCO, autres)
- Périodes à l'étranger (pays, durées)
- Périodes de chômage, maladie, maternité
- Trous de carrière (lacunes non expliquées)
- Points complémentaires (AGIRC-ARRCO, IRCANTEC, RCI, RCO)
- Indicateurs carrière longue (trimestres avant 16/18/20/21 ans)

**⚡ Déclenchement automatique RACL** : Si trimestres validés avant 20 ans détectés → déclencher `SKILL_racl.md`

### Étape 2 — Calcul des âges clés

Utiliser `calcul_analyse_carriere.py` → fonctions :
- `determiner_age_legal_depart(date_naissance)`
- `obtenir_duree_assurance_requise(date_naissance)`
- `calculer_date_effet_retraite(date_naissance)`

Ages à calculer :
1. **Âge légal** de départ (selon génération)
2. **Âge taux plein automatique** = 67 ans
3. **Date d'atteinte du taux plein** (selon trimestres actuels + projection)
4. **Date de départ au plus tôt** (taux plein atteint)

### Étape 3 — Vérification trimestres réputés cotisés

Vérifier les limites réglementaires strictes :

| Période | Limite |
|---|---|
| Service national | Max 4 trimestres |
| Maladie + AT (global) | Max 4 trimestres |
| Maternité | Aucune limite |
| Invalidité | Max 2 trimestres |
| Chômage | Max 4 trimestres |
| AVPF/AVA | Max 4 trimestres |
| C2P | Aucune limite |

**⚠️ ARRÊT CRITIQUE** : Si les trimestres déclarés dépassent ces limites → flag immédiat, ne pas continuer sans validation.

### Étape 4 — Détection des anomalies

Contrôles obligatoires (ARC_C01 à ARC_C07) :

- **ARC_C01** — Cohérence NSS : trimestres maternité uniquement si sexe = F
- **ARC_C02** — Total trimestres : validés ≥ cotisés stricts
- **ARC_C03** — Continuité : aucun trou non expliqué > 4 trimestres sans justification
- **ARC_C04** — Doublons régimes : pas de cotisation simultanée CNAV + régime spécial sans explication
- **ARC_C05** — Cohérence âges : début carrière cohérent avec date de naissance (min 14 ans)
- **ARC_C06** — Trimestres étrangers : pays identifié, statut (UE/Convention/Sans accord) vérifié
- **ARC_C07** — Points complémentaires : plausibilité selon durée et salaire déclaré

### Étape 5 — Estimation pensions

Via `calcul_analyse_carriere.py` → `api_handler(params)` :

Régimes couverts :
- **CNAV** (régime général) : taux liquidation, coefficient proratisation, SAM
- **AGIRC-ARRCO** : valeur point 1,4386 € (nov 2024), règle abattement avril 2024
- **IRCANTEC** : valeur point 0,56357 € (jan 2025)
- **RCI** : valeur point 1,335 € — **pas de surcote**
- **RCO** : valeur point 1,200 €

**Règle abattement AGIRC-ARRCO (avril 2024)** :
- Abattement UNIQUEMENT si départ carrière longue AVANT taux plein
- Pas d'abattement si : taux plein atteint OU départ à l'âge légal OU 67 ans OU inaptitude/invalidité

### Étape 6 — Synthèse et recommandations

Produire obligatoirement :

1. Tableau récapitulatif ages clés (légal / taux plein / départ optimal)
2. Estimation pension mensuelle brute par régime
3. Total pension brute consolidée
4. Liste des alertes détectées (rouge/orange/jaune)
5. Recommandation de départ optimal selon situation
6. Actions requises (pièces manquantes, vérifications à mener)

---

## 🚨 SIGNAUX D'ALERTE

### 🔴 ROUGE — Bloquer la consultation

- Trou de carrière > 8 trimestres sans explication
- Trimestres réputés cotisés dépassant les plafonds réglementaires
- Incohérence NSS / sexe (maternité sur homme)
- Total trimestres validés < trimestres cotisés déclarés
- Début de carrière avant 14 ans (impossible)

### 🟠 ORANGE — Signaler et vérifier

- Trou partiel (4-7 trimestres) sans explication
- Points complémentaires anormalement élevés vs durée de cotisation
- Périodes à l'étranger sans identification du pays
- Carrière longue détectée → vérifier éligibilité RACL avant de continuer

### 🟡 JAUNE — Mentionner au client

- Années incomplètes (1-3 trimestres) non expliquées
- Données SAM non disponibles (estimation pension CNAV impossible)
- Écart > 5% entre trimestres validés déclarés et comptabilisés

---

## ⛔ PIÈGES À ÉVITER

1. **Ne jamais analyser sans demander si la personne est encore en activité** (Étape 0)
2. **Ne pas confondre trimestres validés et trimestres cotisés** — ils sont différents
3. **Ne pas appliquer la surcote au RCI/RCO** — ces régimes n'en ont pas
4. **Ne pas oublier la règle abattement AGIRC-ARRCO avril 2024** — la règle a changé
5. **Ne pas ignorer les trimestres étrangers** — 20% des clients ont des périodes hors France
6. **Ne pas extrapoler le SAM** — si non fourni, indiquer "estimation non disponible"
7. **Vérifier systématiquement les trimestres maternité** — uniquement attribuables aux femmes
8. **Ne pas sauter la détection carrière longue** — déclencher RACL automatiquement si applicable

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_ANALYSE_RELEVE_v2",
  "client": {
    "date_naissance": "JJ/MM/AAAA",
    "generation": 1965
  },
  "trimestres": {
    "valides_actuels": 0,
    "cotises_stricts": 0,
    "reputes_cotises": 0,
    "futurs_projetes": 0,
    "total_projete": 0,
    "requis_taux_plein": 172
  },
  "ages_cles": {
    "age_legal_mois": 0,
    "date_taux_plein": "MM/AAAA",
    "age_taux_plein_automatique": "67 ans"
  },
  "pensions_estimees": {
    "cnav_mensuel": 0,
    "agirc_arrco_mensuel": 0,
    "autres_mensuel": 0,
    "total_brut_mensuel": 0
  },
  "alertes": [
    {
      "code": "ARC_C01",
      "niveau": "ROUGE",
      "message": "Description de l'anomalie détectée"
    }
  ],
  "arret_critique": false,
  "racl_detecte": false,
  "actions_requises": []
}
```

**`arret_critique: true`** si une alerte ROUGE est détectée → stopper le process, notifier Jeff immédiatement.

---

## 🔗 Skills complémentaires

| Situation | Skill à déclencher |
|---|---|
| Trimestres avant 20 ans | `skill_racl/SKILL_racl.md` |
| Périodes à l'étranger | `skill_trimestres_etranger/SKILL_trimestres_etranger.md` |
| Questions rachat trimestres | `skill_vplr/SKILL_vplr.md` |
| Calcul pension base CNAV | `skill_cnav/SKILL_calcul_cnav.md` |
| Calcul complémentaires | `skill_complementaires/SKILL_complementaires.md` |

---

## 📚 Sources réglementaires

- Circulaire CNAV 2024-36 (valeur point complémentaires)
- Circulaire CNAV 2025-14 (revalorisation 2025)
- Loi n°2023-270 du 14/04/2023 (réforme retraites)
- Décret n°2023-436 du 03/06/2023 (RACL)
- `circulaires/circulaire_ages_retraite_trimestres_2024.md`
- `circulaires/VALEURS_REGLEMENTAIRES_2025.md`
