# SKILL : Validation Continuité de Carrière

## Métadonnées

```yaml
skill_id: SKILL_VALIDATION_CONTINUITE_v1
version: 1.0
date: 2025-11-01
origine: Créé suite à l'erreur — 8 trimestres manquants (2000-2001) non signalés
script_python: validation_continuite_carriere.py
```

## Description

Détection EXHAUSTIVE des trous dans la carrière, année par année sans exception. Résout le problème des trous que Claude détectait partiellement (certains repérés, d'autres ratés). Ce skill garantit qu'aucun trou ne passe inaperçu.

## Déclencheurs

- Analyse d'un relevé de carrière (après SKILL_analyse_releve)
- Suspicion de trous de carrière par le consultant
- Écart inexpliqué entre trimestres validés et durée de carrière attendue
- Mots-clés : "trou carrière", "lacune", "période manquante", "trimestres manquants"

---

## 🔍 PRINCIPE : ANALYSE EXHAUSTIVE ANNÉE PAR ANNÉE

**Problème résolu** : Claude analysait les années présentes dans le relevé et signalait les anomalies visibles. Mais il ne vérifiait pas les années ABSENTES du relevé — qui sont des trous complets (0 trimestre).

**Solution** : Parcourir CHAQUE ANNÉE de la carrière (début → aujourd'hui) et tagger explicitement chaque année comme complète, partielle, ou vide.

### Classification des années

| Statut | Définition | Signalement |
|---|---|---|
| Complète | 4 trimestres | Aucun |
| Partielle | 1 à 3 trimestres | Trou partiel |
| Vide | 0 trimestre (présent dans données) | Trou complet |
| Absente | Année non présente dans le relevé | Trou complet |

---

## 📊 CLASSIFICATION DES TROUS

### Gravité

| Gravité | Critère | Action |
|---|---|---|
| **CRITIQUE** | ≥ 8 trimestres manquants OU ≥ 2 années complètes absentes | BLOQUER — interroger le client obligatoirement |
| **IMPORTANT** | 4 à 7 trimestres manquants | Signaler — vérification requise |
| **MINEUR** | 1 à 3 trimestres manquants | Mentionner — vérification conseillée |

### Fusion des trous consécutifs

Les trous consécutifs de même type sont fusionnés pour faciliter la lecture :
- Années 2000 absente + 2001 absente → "Trou 2000-2001 : 8 trimestres manquants"

---

## 📋 PROTOCOLE D'ANALYSE

**Étape 1** — Définir la plage : `année_début_carrière → année_actuelle`

**Étape 2** — Pour chaque année :
- Est-elle présente dans le relevé ?
  - Non → trou complet (4 trimestres manquants)
  - Oui → combien de trimestres ? Si < 4 → trou partiel

**Étape 3** — Fusionner les trous consécutifs de même type

**Étape 4** — Évaluer la gravité de chaque trou

**Étape 5** — Générer la timeline visuelle

**Étape 6** — Retourner le résultat structuré

---

## 🗺️ FORMAT TIMELINE VISUELLE

Produit une représentation par tranches de 10 ans :

```
Légende : ████ Année complète | ▓▓▓▓ Partielle | ░░░░ Vide/Absente

1981-1990: ████ ████ ████ ░░░░ ░░░░ ████ ████ ████ ████ ████
1991-2000: ████ ████ ████ ████ ████ ████ ████ ████ ████ ████
2001-2010: ████ ████ ████ ████ ▓▓▓▓ ████ ████ ████ ████ ████
```

---

## ⚠️ RÈGLES ABSOLUES

- **Ne jamais sauter une année** entre début de carrière et date actuelle
- **Ne pas supposer qu'une année absente = données non fournies** : c'est un trou sauf preuve contraire
- **Toujours interroger le client** sur les trous CRITIQUE et IMPORTANT avant de finaliser le calcul
- **Ne pas corriger les trimestres** sans confirmation explicite du client

---

## 📤 FORMAT DE SORTIE JSON OBLIGATOIRE

```json
{
  "skill_id": "SKILL_VALIDATION_CONTINUITE_v1",
  "plage_analysee": {
    "annee_debut": 1981,
    "annee_fin": 2025,
    "nb_annees_total": 44
  },
  "continuite_ok": false,
  "trous_detectes": [
    {
      "annee_debut": 2000,
      "annee_fin": 2001,
      "nb_annees": 2,
      "trimestres_manquants": 8,
      "type": "COMPLET",
      "gravite": "CRITIQUE",
      "description": "Années 2000-2001 : 8 trimestres manquants sur 2 ans"
    }
  ],
  "total_trimestres_manquants": 8,
  "timeline_visuelle": "...",
  "alertes": [
    {
      "code": "CC_C01",
      "niveau": "CRITIQUE",
      "message": "Trou de carrière 2000-2001 : 8 trimestres. Interroger le client obligatoirement."
    }
  ],
  "arret_critique": true,
  "action_requise": "Interroger le client sur les périodes 2000-2001 avant de poursuivre"
}
```

---

## 📚 Origine et contexte

Créé suite à une erreur réelle : 8 trimestres manquants entre 1999 et 2002 (années 2000-2001 absentes du relevé) n'avaient pas été détectés lors d'une consultation. Le consultant avait signalé d'autres trous visibles (2012, 2018) mais raté ceux-ci car ils n'apparaissaient tout simplement pas dans les données fournies.

Ce skill rend l'omission structurellement impossible.
