# SKILL : Normalisation de Question

## Métadonnées

```yaml
skill_id: SKILL_NORMALISATION_QUESTION_v1
version: 1.0
date: 2026-04-06
position: PRÉ-PROCESSING — s'exécute AVANT le routing vers tout autre skill
utilisateurs_cibles: experts-comptables, utilisateurs non-experts retraite
script_python: null
regles_json: normalisation_regles.json
```

## Description

Intercepter la question brute de l'utilisateur, la valider, l'enrichir avec le contexte client disponible (FrozenData), et produire une question normalisée prête à router vers le bon skill. Si la question est trop vague, demander une seule question de clarification ciblée. Si hors périmètre, retourner un message d'orientation court.

**Principe** : l'expert-comptable tape ce qu'il veut en langage naturel. Ce skill s'assure que le reste du pipeline ne reçoit jamais une question floue, mal orientée ou incohérente avec le profil du client.

---

## 🔄 FLUX DE TRAITEMENT

```
[Question brute] + [FrozenData client]
        ↓
  [1] Détection domaine
        ↓
  [2] Évaluation qualité (score 0-100)
        ↓
   ┌────┴────┐
score ≥ 70   score 40-69    score < 40
    ↓            ↓               ↓
Reformulation  1 question    Message
  silencieuse  clarification  d'orientation
    ↓            ↓               ↓
[Question normalisée] → Routing skill
```

---

## 🗂️ DOMAINES COUVERTS

| Domaine | Skill cible | Mots-clés déclencheurs |
|---|---|---|
| `racl` | skill_racl | carrière longue, départ anticipé, partir avant, commencé jeune, avant 60 ans |
| `pension_estimation` | skill_cnav + skill_complementaires | combien il va toucher, montant pension, calcul retraite, estimation |
| `rachat_trimestres` | skill_vplr | racheter, trimestres manquants, coût rachat, VPLR |
| `trimestres_etranger` | skill_trimestres_etranger | étranger, expatrié, hors France, a travaillé à, période internationale |
| `retraite_progressive` | skill_retraite_progressive | temps partiel, retraite progressive, réduire son activité |
| `cumul_emploi_retraite` | skill_cumul_emploi_retraite | continuer à travailler, cumuler, après la retraite, reprise activité |
| `analyse_carriere` | skill_analyse_releve | relevé de carrière, analyse de situation, trous, lacunes, trimestres validés |
| `date_depart` | skill_cnav | quand partir, quel âge, date optimale, à partir de quand |

---

## 📐 RÈGLES DE REFORMULATION

### Enrichissement avec FrozenData

La question normalisée doit toujours inclure les données pertinentes disponibles :

```
Question brute : "Est-ce qu'il peut partir en carrière longue ?"
Question normalisée : "M. Dupont, né le 15/03/1965, avec 173 trimestres validés
dont 6 avant ses 18 ans — est-il éligible au dispositif RACL ?
Détailler les conditions d'éligibilité et l'âge de départ possible."
```

**Données à injecter selon le domaine** :

| Domaine | Données FrozenData à injecter |
|---|---|
| racl | date_naissance, trimestres_avant_16/18/20/21_ans, trimestres_cotises |
| pension_estimation | date_naissance, trimestres_valides, points_complementaires, sam |
| rachat_trimestres | trimestres_manquants, age_actuel, age_depart_cible |
| trimestres_etranger | pays, periodes_etrangeres, trimestres_francais |
| date_depart | date_naissance, trimestres_actuels, situation_activite |

### Glossaire expert-comptable → EOR

| Terme expert-comptable | Terme EOR correct |
|---|---|
| "retraite de base" | pension CNAV (régime général) |
| "complémentaire" | AGIRC-ARRCO / IRCANTEC / RCI selon statut |
| "avoir le plein" | atteindre le taux plein |
| "trimestres qui manquent" | trimestres manquants pour taux plein |
| "points retraite" | points AGIRC-ARRCO ou IRCANTEC (préciser) |
| "départ à 62 ans" | départ à l'âge légal (né avant 1968) |
| "la réforme" | réforme retraites 2023 — Loi n°2023-270 du 14/04/2023 |
| "racheter des trimestres" | rachat VPLR (Versement Pour la Retraite) |
| "travailler à mi-temps" | retraite progressive (si conditions remplies) |
| "continuer à travailler après" | cumul emploi-retraite (CER) |

---

## ❓ RÈGLES DE CLARIFICATION

**Déclencher une clarification si** (score 40-69) :

1. Domaine non détectable avec certitude
2. Question valide mais donnée critique absente de FrozenData
3. Ambiguïté sur la personne (client ou conjoint ?)
4. Question multi-domaines sans priorité claire

**Format de la clarification** — 1 question MAX, courte, fermée si possible :

```
Exemples :
"Pour cette question : s'agit-il du client lui-même ou de son conjoint ?"
"Vous demandez un calcul de pension estimée, ou l'éligibilité à un départ anticipé ?"
"Votre client est salarié du privé, indépendant, ou fonctionnaire ?"
```

**Ne jamais** poser plus d'une question. Ne jamais utiliser de jargon EOR dans la clarification.

---

## 🚫 RÈGLES D'ORIENTATION (score < 40)

Si la question est hors périmètre ou incohérente avec le profil :

```
Exemples de messages courts :
"Cette question concerne la fiscalité, pas la retraite.
 Pour les aspects fiscaux, consultez votre direction fiscale."

"Je ne peux pas répondre à cette question sans le relevé de carrière du client.
 Importez d'abord le relevé pour accéder à cette analyse."

"Cette question concerne un régime spécial (fonctionnaire, SNCF...) non couvert
 par cet outil. Contactez EOR pour une consultation spécialisée."
```

**Toujours** terminer le message d'orientation par une action concrète (que faire ensuite).

---

## 📊 CALCUL DU SCORE DE CONFIANCE

| Critère | Points |
|---|---|
| Domaine identifié avec certitude | +30 |
| Données FrozenData suffisantes pour répondre | +30 |
| Termes reconnus (glossaire ou mots-clés domaine) | +20 |
| Question grammaticalement cohérente | +10 |
| Aucune ambiguïté sur la personne concernée | +10 |
| **Total possible** | **100** |

Seuils : ≥ 70 → reformulation | 40-69 → clarification | < 40 → orientation

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_NORMALISATION_QUESTION_v1",
  "question_brute": "Est-ce qu'il peut partir en carrière longue ?",
  "score_confiance": 85,
  "domaine_detecte": "racl",
  "skill_cible": "skill_racl",
  "mode": "reformulation",
  "question_normalisee": "M. Dupont, né le 15/03/1965, avec 173 trimestres validés dont 6 avant ses 18 ans — est-il éligible au dispositif RACL ? Détailler les conditions d'éligibilité et l'âge de départ possible.",
  "clarification_requise": false,
  "question_clarification": null,
  "message_orientation": null,
  "donnees_injectees": ["date_naissance", "trimestres_valides", "trimestres_avant_18_ans"],
  "alertes": [],
  "arret_critique": false
}
```

**Si clarification requise** (`mode: "clarification"`) :
- `question_normalisee` = null
- `question_clarification` = la question à poser à l'expert-comptable
- Attendre la réponse avant de relancer le pipeline

**Si hors périmètre** (`mode: "orientation"`) :
- `question_normalisee` = null
- `message_orientation` = le message court à afficher
- `arret_critique` = false (pas une erreur, juste un hors-scope)

---

## ⛔ CE QUE CE SKILL NE FAIT PAS

- Ne produit pas de réponse à la question — il la normalise uniquement
- Ne calcule rien
- Ne modifie pas FrozenData
- Ne pose jamais plusieurs questions de clarification à la fois
- Ne rejette jamais une question sans proposer une alternative
