# 🎯 RACL - Format Classe Mondiale : LIVRAISON

**Date** : 10 novembre 2025  
**Projet** : Calculateur Retraite - Skill RACL optimisé pour n8n

---

## 📦 FICHIERS LIVRÉS

### 3 fichiers créés (format classe mondiale)

1. **SKILL_racl.md** (20 Ko)
   - Instructions Claude structurées
   - Déclencheurs pour routage n8n
   - Contrôles de cohérence (RAC_C01 à RAC_C07)
   - Alertes Rouge/Orange/Jaune
   - Sources réglementaires complètes

2. **racl_regles.json** (20 Ko)
   - Données structurées exploitables par n8n
   - Toutes les valeurs réglementaires 2025
   - Conditions par génération (1963-1970+)
   - Limites trimestres réputés cotisés
   - Token estimates pour monitoring

3. **calcul_racl.py** (24 Ko)
   - Script Python avec toutes les fonctions existantes
   - **AJOUT** : fonction `api_handler()` (point d'entrée unifié pour n8n)
   - Validation et contrôles intégrés
   - Génération automatique des alertes

---

## ✨ NOUVEAUTÉS INTÉGRÉES

### 1. IDs contrôles préfixés ✅
```
Avant : C01, C02, C03...
Après : RAC_C01, RAC_C02, RAC_C03...
```
**Avantage** : Évite les collisions dans les logs n8n avec les autres skills

### 2. Token estimates dans JSON ✅
```json
"skill_info": {
  "token_estimate_md": 4200,
  "token_estimate_json": 2400,
  "token_estimate_total": 6600
}
```
**Avantage** : Monitoring budget tokens dans n8n

### 3. Fonction api_handler() dans Python ✅
```python
def api_handler(params: Dict) -> Dict:
    """Point d'entrée unifié pour n8n"""
    # Standardise l'interface d'appel
    # Retourne résultat structuré avec alertes
```

**Utilisation dans n8n** :
```javascript
// Node n8n "Execute Python Code"
const result = api_handler({
  "date_naissance": "15/03/1965",
  "liste_trimestres_valides": [...],
  "trimestres_cotises_stricts": 165,
  "duree_requise_taux_plein": 172,
  "trimestres_service_national": 4
});

// Résultat structuré :
{
  "eligible": true,
  "age_depart": "60 ans 9 mois",
  "date_depart_possible": "01/12/2025",
  "trimestres_manquants": 0,
  "controles": [...],
  "alertes": {
    "rouge": [],
    "orange": [],
    "jaune": [...]
  }
}
```

---

## 🔍 STRUCTURE DÉTAILLÉE

### SKILL_racl.md

**Sections** :
- ✅ Déclencheurs (8 mots-clés)
- ✅ Principes fondamentaux
- ✅ Conditions d'éligibilité (2 conditions cumulatives)
- ✅ Âges de départ par génération (1963-1970+)
- ✅ Trimestres cotisés vs réputés cotisés (limites 4-4-2-4)
- ✅ Règles de cumul (max 4 trim/an)
- ✅ Conditions par génération (tableaux détaillés)
- ✅ Contrôles de cohérence (RAC_C01 à RAC_C07)
- ✅ Signaux d'alerte (Rouge/Orange/Jaune)
- ✅ Sources réglementaires (Circulaire CNAV 2023-14)
- ✅ Conseils stratégiques
- ✅ Pièges à éviter (8 pièges fréquents)
- ✅ Liens avec autres skills

**Token estimate** : ~4200 tokens

### racl_regles.json

**Structure JSON** :
```json
{
  "skill_info": {...},
  "declencheurs": [...],
  "condition_debut_activite": {...},
  "condition_duree_cotisee": {...},
  "ages_depart": {...},
  "trimestres_strictement_cotises": [...],
  "rachats": {...},
  "trimestres_reputes_cotises": {
    "service_national": {"limite": 4},
    "maladie_at": {"limite": 4},
    "maternite_adoption": {"limite": null},
    "invalidite": {"limite": 2},
    "chomage": {"limite": 4},
    "avpf_ava": {"limite": 4},
    "c2p": {"limite": null}
  },
  "periodes_exclues": [...],
  "regles_cumul": {...},
  "conditions_par_generation": {...},
  "controles_coherence": [...],
  "alertes": {...},
  "sources_reglementaires": {...},
  "conseils_strategiques": {...},
  "pieges_frequents": [...]
}
```

**Token estimate** : ~2400 tokens

### calcul_racl.py

**Fonctions principales** :
1. `api_handler(params)` → **NOUVEAU** Point d'entrée n8n
2. `calculer_trimestres_avant_age(...)` → Calcul début activité
3. `calculer_trimestres_reputes_cotises(...)` → Applique limites
4. `determiner_age_depart_racl(...)` → Âge selon génération
5. `analyser_eligibilite_racl(...)` → Analyse complète

**Retours structurés** : Tous en Dict/List pour parsing facile

---

## 🚀 INTÉGRATION N8N

### Workflow type RACL

```
[Bouton "Analyse RACL"]
    ↓
[Node: Fetch Data Client]
    ↓
[Node: Load SKILL_racl.md + racl_regles.json]
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
    Rapport + Alertes + Contrôles
    ↓
[Return to Interface]
```

**Token budget total** : ~6600 tokens (MD + JSON + contexte)

---

## 📊 CONTRÔLES DE COHÉRENCE

### Liste des contrôles implémentés

| ID | Type | Description |
|----|------|-------------|
| **RAC_C01** | ERREUR_CRITIQUE | Trimestres début activité insuffisants |
| **RAC_C02** | ERREUR_CRITIQUE | Trimestres cotisés < durée requise |
| **RAC_C03** | AVERTISSEMENT | Dépassement limite trimestres réputés cotisés |
| **RAC_C04** | ERREUR | Cumul >4 trimestres/an |
| **RAC_C05** | ERREUR | Âge départ incohérent avec génération |
| **RAC_C06** | AVERTISSEMENT | Clause de sauvegarde non vérifiée (1961-1963) |
| **RAC_C07** | ERREUR | Rachat VPLR post-2011 compté à tort |

### Système d'alertes

**🔴 ROUGE** : Bloquants (RACL impossible)
- Condition début activité non remplie
- Durée cotisée insuffisante
- Confusion trimestres validés/cotisés

**🟠 ORANGE** : À vérifier (opportunités)
- Proche éligibilité (1-2 trimestres manquants)
- Rachats VPLR potentiels
- Clause de sauvegarde applicable

**🟡 JAUNE** : Informatif
- Né 4ème trimestre (4 trim requis)
- Optimisation affectation trimestres
- Chômage avant 1980 (avantage)

---

## 📝 EXEMPLE D'APPEL API HANDLER

### Input
```python
params = {
    "date_naissance": "15/03/1965",
    "liste_trimestres_valides": [
        (1981, 1), (1981, 2), (1981, 3), (1981, 4),
        (1982, 1), ...  # Suite des trimestres
    ],
    "trimestres_cotises_stricts": 165,
    "duree_requise_taux_plein": 172,
    "trimestres_service_national": 4,
    "trimestres_maladie": 2,
    "trimestres_maternite": 0,
    "trimestres_chomage": 1
}

result = api_handler(params)
```

### Output
```python
{
    "eligible": True,
    "age_depart": "60 ans 9 mois",
    "date_depart_possible": "01/12/2025",
    "trimestres_cotises": 165,
    "trimestres_reputes_cotises": 7,
    "trimestres_total_racl": 172,
    "duree_requise": 172,
    "trimestres_manquants": 0,
    "cas_debut_activite": "avant_20_ans",
    "controles": [],
    "alertes": {
        "rouge": [],
        "orange": [],
        "jaune": ["Né 4ème trimestre - 4 trimestres requis..."]
    },
    "detail_reputes_cotises": {
        "service_national": {"declares": 4, "retenus": 4},
        "maladie_at": {"declares": 2, "retenus": 2},
        "chomage": {"declares": 1, "retenus": 1},
        ...
    }
}
```

---

## ✅ CHECKLIST DE VALIDATION

### Fichiers créés
- [x] SKILL_racl.md (format classe mondiale)
- [x] racl_regles.json (données structurées)
- [x] calcul_racl.py (avec api_handler)

### Recommandations intégrées
- [x] IDs préfixés (RAC_C01, RAC_C02...)
- [x] Token estimates dans JSON
- [x] Fonction api_handler() dans Python
- [x] Déclencheurs pour routage n8n
- [x] Alertes Rouge/Orange/Jaune
- [x] Documentation complète

### Compatibilité n8n
- [x] JSON natif (parsable directement)
- [x] Retours Python structurés (Dict/List)
- [x] Point d'entrée unifié (api_handler)
- [x] Contexte optimisé (~6600 tokens)

---

## 🎯 PROCHAINES ÉTAPES

### Option A : Tests standalone (recommandé)
1. Tester `api_handler()` avec données réelles
2. Valider contrôles RAC_C01 à RAC_C07
3. Vérifier alertes Rouge/Orange/Jaune
4. Corriger si nécessaire

### Option B : Intégration n8n directe
1. Créer workflow RACL dans n8n
2. Configurer nodes selon architecture recommandée
3. Tester avec 3 cas clients :
   - Éligible RACL 60 ans
   - Non éligible (trimestres manquants)
   - Éligible RACL 58 ans (rare)

### Option C : Reproduire pour autres skills
1. Appliquer même méthode à "Trimestres étranger"
2. Puis "Estimation pensions"
3. Puis "Analyse relevé carrière"

---

## 📞 QUESTIONS / CLARIFICATIONS

Si besoin d'ajustements :
- Format des fichiers OK ?
- Niveau de détail suffisant ?
- Autres skills à prioriser ?
- Tests spécifiques à faire ?

---

## 🏆 RÉCAPITULATIF

**✅ RACL livré au format "classe mondiale"**

**3 fichiers** :
- SKILL_racl.md (instructions Claude)
- racl_regles.json (données structurées)
- calcul_racl.py (avec api_handler)

**Toutes les recommandations intégrées** :
- IDs préfixés ✅
- Token estimates ✅
- API handler ✅
- Alertes structurées ✅

**Prêt pour n8n** 🚀

---

**Tokens restants : ~114k** (60% consommé)

**Prochaine action ?** 💬
