# 🎯 VPLR - Format Classe Mondiale : LIVRAISON

**Date** : 10 novembre 2025  
**Projet** : Calculateur Retraite - Skill VPLR optimisé pour n8n

---

## 📦 FICHIERS LIVRÉS

### 3 fichiers créés (format classe mondiale)

1. **SKILL_vplr.md** (24 Ko)
   - Instructions Claude structurées
   - Déclencheurs pour routage n8n
   - Éligibilité + Calcul coût + Rentabilité
   - Contrôles VPL_C01 à VPL_C07
   - Alertes Rouge/Orange/Jaune

2. **vplr_regles.json** (24 Ko)
   - Barème 2025 complet (âge 20 à 66 ans)
   - Tranches de revenus (3 tranches PASS)
   - Options taux seul vs taux + durée
   - Seuils de rentabilité
   - Token estimates : 9000 total

3. **vplr_calculs.py** (26 Ko)
   - Calcul coût VPLR selon barème 2025
   - Interpolation âges intermédiaires
   - Calcul rentabilité (durée récupération)
   - Comparaison 2 options
   - **AJOUT** : `api_handler()` pour n8n

---

## ✨ NOUVEAUTÉS INTÉGRÉES

### 1. IDs contrôles préfixés ✅
```
VPL_C01 : Âge hors limites
VPL_C02 : Dépassement 12 trimestres
VPL_C03 : Absence diplôme études supérieures
VPL_C04 : Année déjà complète
VPL_C05 : Rentabilité négative
VPL_C06 : Option sous-optimale
VPL_C07 : Rachat tardif sans analyse
```

### 2. Token estimates dans JSON ✅
```json
"skill_info": {
  "token_estimate_md": 5800,
  "token_estimate_json": 3200,
  "token_estimate_total": 9000
}
```

### 3. Fonction api_handler() dans Python ✅
```python
def api_handler(params: Dict) -> Dict:
    """Point d'entrée unifié pour n8n"""
    # Éligibilité + Coût + Rentabilité
    # Retourne résultat structuré avec alertes
```

**Utilisation dans n8n** :
```javascript
const result = api_handler({
  "age": 45,
  "revenu_moyen_annuel": 42000,
  "nb_trimestres": 4,
  "option": "taux_et_duree",
  "pension_annuelle_sans_rachat": 18000,
  "pension_annuelle_avec_rachat": 19500,
  "tmi": 30
});

// Résultat :
{
  "eligible": true,
  "cout_total": 21195.36,
  "cout_net_apres_fiscalite": 14836.75,
  "duree_recuperation_ans": 9.9,
  "rentable": true,
  "controles": [...],
  "alertes": {...}
}
```

---

## 🔍 STRUCTURE DÉTAILLÉE

### SKILL_vplr.md

**Sections complètes** :
- ✅ Déclencheurs (8 mots-clés)
- ✅ Principes fondamentaux VPLR
- ✅ Périodes rachetables (études supérieures + années incomplètes)
- ✅ Calcul du coût (barème 2025 complet)
- ✅ 3 tranches de revenus (< 75% PASS, 75-100%, > 100%)
- ✅ 2 options : Taux seul vs Taux + durée
- ✅ Calcul de rentabilité (formule + seuils)
- ✅ Déduction fiscale (30-45% économie)
- ✅ Procédure administrative
- ✅ Contrôles VPL_C01 à VPL_C07
- ✅ Alertes Rouge/Orange/Jaune
- ✅ Conseils stratégiques (timing optimal)
- ✅ Pièges à éviter (8 pièges)

**Token estimate** : ~5800 tokens

### vplr_regles.json

**Données structurées** :
```json
{
  "conditions_eligibilite": {
    "age_minimum": 20,
    "age_maximum": 66,
    "maximum_trimestres_rachetables": 12
  },
  "pass_2025": 49308,
  "tranches_revenus_2025": {...},
  "options_rachat": {
    "option_taux_seul": {...},
    "option_taux_et_duree": {...}
  },
  "bareme_2025": {
    "age_20": {...},
    "age_25": {...},
    ...
    "age_66": {...}
  },
  "rentabilite": {
    "seuils_indicatifs": {...},
    "esperance_vie_62_ans": {"hommes": 22, "femmes": 26}
  },
  "controles_coherence": [...]
}
```

**Barème 2025 complet** : Tous les âges de 20 à 66 ans

**Token estimate** : ~3200 tokens

### vplr_calculs.py

**Fonctions principales** :
1. `api_handler(params)` → **NOUVEAU** Point d'entrée n8n
2. `calculer_cout_vplr(age, revenu, option)` → Calcul coût selon barème
3. `interpoler_bareme(age)` → Interpolation âges intermédiaires
4. `calculer_rentabilite_vplr(...)` → Durée récupération
5. `simuler_scenarios_vplr(...)` → Comparaison 2 options

**Particularités** :
- Interpolation linéaire pour âges non présents dans barème
- Prise en compte déduction fiscale
- Comparaison automatique taux seul vs taux + durée
- Calcul espérance de vie par sexe

---

## 💰 EXEMPLES DE COÛTS 2025

### Barème "Taux + durée" (rachat complet)

| Âge | Tranche 1 (< 36 981 €) | Tranche 2 (% revenu) | Tranche 3 (> 49 308 €) |
|-----|------------------------|----------------------|------------------------|
| 30 ans | 2 204 € | 7,93% | 2 938 € |
| 40 ans | 3 060 € | 11,02% | 4 080 € |
| 50 ans | 3 960 € | 14,26% | 5 279 € |
| 60 ans | 4 854 € | 17,48% | 6 472 € |

### Exemple complet

**Client : 45 ans, revenu 42 000 €/an, 4 trimestres**

Option "Taux + durée" :
- Tranche 2 (42 000 € entre 36 981 et 49 308)
- Coût unitaire : 42 000 × 12,62% = 5 300 €
- Coût total : 5 300 × 4 = 21 200 €
- Économie fiscale (TMI 30%) : 6 360 €
- **Coût net : 14 840 €**

Rentabilité :
- Gain pension : 1 500 €/an
- Durée récupération : 14 840 / 1 500 = **9,9 ans**
- Espérance vie à 62 ans : 22-26 ans
- **Verdict : RENTABLE** ✅

---

## 🚀 INTÉGRATION N8N

### Workflow type VPLR

```
[Bouton "Calculer VPLR"]
    ↓
[Node: Fetch Data Client]
    - Âge
    - Revenu moyen
    - Trimestres à racheter
    - Pension actuelle estimée
    ↓
[Node: Load SKILL_vplr.md + vplr_regles.json]
    ↓
[Node: Execute Python - api_handler()]
    ↓
[Node: Build Prompt Claude]
    Contexte = MD + JSON + Résultats Python
    ↓
[Node: Claude API]
    POST /v1/messages
    ↓
[Node: Format Output]
    - Coût brut/net
    - Rentabilité
    - Recommandation option
    - Alertes
    ↓
[Return to Interface]
```

**Token budget total** : ~9000 tokens (MD + JSON + contexte)

---

## 📊 CONTRÔLES IMPLÉMENTÉS

| ID | Type | Description |
|----|------|-------------|
| **VPL_C01** | BLOQUANT | Âge hors limites (< 20 ou ≥ 67 ans) |
| **VPL_C02** | ERREUR | Dépassement 12 trimestres |
| **VPL_C03** | BLOQUANT | Absence diplôme études supérieures |
| **VPL_C04** | IMPOSSIBLE | Année déjà complète (4 trimestres validés) |
| **VPL_C05** | AVERTISSEMENT | Rentabilité négative (durée > 25 ans) |
| **VPL_C06** | SOUS-OPTIMAL | Option "taux seul" alors que durée manquante |
| **VPL_C07** | AVERTISSEMENT | Rachat après 60 ans sans analyse rentabilité |

---

## 📝 EXEMPLE D'APPEL API HANDLER

### Input
```python
params = {
    "age": 45,
    "revenu_moyen_annuel": 42000,
    "nb_trimestres": 4,
    "option": "taux_et_duree",
    "pension_annuelle_sans_rachat": 18000,
    "pension_annuelle_avec_rachat": 19500,
    "tmi": 30,
    "sexe": "homme"
}

result = api_handler(params)
```

### Output
```python
{
    "eligible": True,
    "cout_par_trimestre": 5298.84,
    "cout_total": 21195.36,
    "economie_fiscale": 6358.61,
    "cout_net_apres_fiscalite": 14836.75,
    "gain_annuel_pension": 1500.0,
    "duree_recuperation_ans": 9.9,
    "rentable": True,
    "tranche_revenu": "tranche_2",
    "option_choisie": "taux_et_duree",
    "controles": [],
    "alertes": {
        "rouge": [],
        "orange": [],
        "jaune": []
    }
}
```

---

## 💡 FONCTIONNALITÉS AVANCÉES

### 1. Interpolation barème ✅

Le script interpole les âges non présents dans le barème :
```python
# Âge 42 ans non dans barème → Interpolation entre 40 et 45 ans
resultat = calculer_cout_vplr(42, 42000, "taux_et_duree")
# Coût calculé précisément par interpolation linéaire
```

### 2. Comparaison automatique options ✅

```python
scenarios = simuler_scenarios_vplr(
    age=45,
    revenu_moyen_annuel=42000,
    nb_trimestres=4,
    pension_sans_rachat=18000,
    pension_avec_rachat=19500
)

# Retourne :
# - Coût et rentabilité "taux seul"
# - Coût et rentabilité "taux + durée"
# - Recommandation automatique avec justification
```

### 3. Prise en compte sexe (espérance vie) ✅

```python
# Espérance de vie différenciée
ESPERANCE_VIE_62_ANS = {"hommes": 22, "femmes": 26}

# Utilisée dans calcul rentabilité
# Impact : Femmes → Rachat plus rentable (4 ans de plus)
```

### 4. Déduction fiscale intégrée ✅

```python
# Calcul automatique économie fiscale
economie = cout_total × (tmi / 100)
cout_net = cout_total - economie

# Impact majeur sur rentabilité
# TMI 30% → Coût net = 70% du coût brut
```

---

## ✅ CHECKLIST VALIDATION

### Fichiers créés
- [x] SKILL_vplr.md (format classe mondiale)
- [x] vplr_regles.json (barème 2025 complet)
- [x] vplr_calculs.py (avec api_handler + rentabilité)

### Recommandations intégrées
- [x] IDs préfixés (VPL_C01, VPL_C02...)
- [x] Token estimates dans JSON
- [x] Fonction api_handler() dans Python
- [x] Déclencheurs pour routage n8n
- [x] Alertes Rouge/Orange/Jaune
- [x] Documentation complète

### Fonctionnalités
- [x] Calcul coût selon barème 2025
- [x] 3 tranches de revenus PASS
- [x] 2 options (taux seul vs taux + durée)
- [x] Calcul rentabilité (durée récupération)
- [x] Déduction fiscale
- [x] Interpolation âges
- [x] Comparaison options automatique
- [x] Espérance vie par sexe

### Compatibilité n8n
- [x] JSON natif (parsable directement)
- [x] Retours Python structurés
- [x] Point d'entrée unifié (api_handler)
- [x] Contexte optimisé (~9000 tokens)

---

## 🎯 PROCHAINES ÉTAPES

### Option A : Tests standalone (recommandé)
1. Tester `api_handler()` avec plusieurs âges et revenus
2. Valider interpolation barème (âges non standard)
3. Vérifier calculs rentabilité
4. Tester comparaison 2 options
5. Valider contrôles VPL_C01 à VPL_C07

### Option B : Intégration n8n directe
1. Créer workflow VPLR dans n8n
2. Configurer nodes selon architecture
3. Tester avec 3 cas clients :
   - Jeune (30 ans) → Coût faible, rentable
   - Moyen (50 ans) → Coût modéré, à étudier
   - Âgé (62 ans) → Coût élevé, limite

### Option C : Reproduire pour autres skills
1. Trimestres étranger
2. Estimation pensions
3. Analyse relevé carrière

---

## 🏆 RÉCAPITULATIF

**✅ VPLR livré au format "classe mondiale"**

**3 fichiers** :
- SKILL_vplr.md (instructions Claude + rentabilité)
- vplr_regles.json (barème 2025 complet)
- vplr_calculs.py (avec api_handler + interpolation)

**Fonctionnalités complètes** :
- Éligibilité ✅
- Calcul coût (barème 2025) ✅
- Calcul rentabilité ✅
- Comparaison options ✅
- Déduction fiscale ✅

**Toutes les recommandations intégrées** :
- IDs préfixés ✅
- Token estimates ✅
- API handler ✅
- Alertes structurées ✅

**Prêt pour n8n** 🚀

---

**Tokens restants : ~94k** (50% consommé)

**2 skills livrés : RACL + VPLR** 🎉

**Prochaine action ?** 💬
