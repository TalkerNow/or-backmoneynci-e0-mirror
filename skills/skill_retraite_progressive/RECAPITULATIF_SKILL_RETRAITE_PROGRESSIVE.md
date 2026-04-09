# 🎯 SKILL RETRAITE PROGRESSIVE - RÉCAPITULATIF

**Date de création** : 10/11/2025  
**Version** : 1.0 - Classe mondiale  
**Projet** : Calculateur Retraite - EOR

---

## ✅ FICHIERS CRÉÉS

### 1️⃣ SKILL_retraite_progressive.md (80 Ko)

**Contenu** :
- 📋 Objectif du skill et déclencheurs
- 📚 Contexte réglementaire complet
- ✅ Les 3 conditions d'éligibilité (âge, durée, quotité)
- 📐 Calcul de la fraction de pension
- 🔄 Service, modification, suspension, suppression
- 🎯 Liquidation définitive
- 🔍 Contrôles de cohérence (RP_C01 à RP_C12)
- 💡 Cas particuliers (invalidité, multi-employeurs, assistantes maternelles)
- ⚠️ Erreurs à éviter
- 🎓 Exemples pratiques complets
- 📊 Comparaisons RP vs Retraite complète vs Cumul emploi-retraite
- 📋 Checklist de validation

**Format** : Markdown structuré, format "classe mondiale"

**Usage** : Documentation de référence pour les consultants EOR

---

### 2️⃣ retraite_progressive_regles.json (20 Ko)

**Contenu** :
- Métadonnées du skill
- Déclencheurs (mots-clés)
- Conditions d'éligibilité structurées
- Règles de calcul (fraction, pension provisoire, définitive)
- Contrôles de cohérence avec préfixes **RP_C01** à **RP_C12**
- Alertes métier (Rouge/Orange/Jaune)
- Cas particuliers (pension invalidité, multi-employeurs)
- Documents nécessaires
- Comparaisons RP vs autres dispositifs
- Évolutions futures (Phase 2, Phase 3)

**Format** : JSON structuré avec IDs préfixés

**Usage** : Configuration pour N8N, validations automatiques

---

### 3️⃣ calcul_retraite_progressive.py (25 Ko)

**Contenu** :
- Constantes réglementaires (âges légaux, durées requises, quotités)
- Fonctions de calcul :
  - `calculer_age_rp_minimal()` : Âge minimum RP
  - `verifier_duree_assurance()` : Vérif 150 trimestres
  - `calculer_quotite_travail()` : Quotité mono-employeur
  - `calculer_quotite_multi_employeurs()` : Quotité multi-employeurs
  - `calculer_pension_entiere_provisoire()` : Pension entière provisoire
  - `calculer_fraction_pension()` : Fraction versée
  - `calculer_montant_rp()` : Montant RP mensuel
  - `verifier_eligibilite_complete()` : Éligibilité globale
  - `calculer_retraite_progressive_complete()` : Calcul complet
- **`api_handler()`** : Point d'entrée N8N
- `formater_sortie_client()` : Formatage pour le client
- Exemples d'utilisation en bas du script

**Format** : Python 3.8+, docstrings complètes

**Usage** : Appelé par N8N via `api_handler(params)`

---

## 🔧 INTÉGRATION N8N

### Appel de la fonction api_handler

```python
import calcul_retraite_progressive as rp

# Paramètres d'entrée
params = {
    "date_naissance": "15/03/1965",
    "trimestres_tous_regimes": 165,
    "trimestres_rg": 158,
    "sam": 28000,
    "emplois": [
        {
            "heures_tp": 24,
            "heures_tc": 35,
            "type_employeur": "entreprise"
        }
    ],
    "majoration_enfants": 0
}

# Appel
resultat = rp.api_handler(params)

# Résultat
{
    "success": True,
    "eligible": True,
    "conditions": {
        "age": {...},
        "duree": {...},
        "quotite": {...}
    },
    "calculs": {
        "pension_entiere": {...},
        "fraction": {...},
        "montant_rp": {...}
    },
    "controles": [...],
    "alertes": [...],
    "recommandation": "Éligible à la retraite progressive",
    "token_estimate": 500
}
```

---

## 📊 EXEMPLES COUVERTS

### Exemple 1 : Mono-employeur éligible
- Né en 1965 (60 ans)
- 165 trimestres tous régimes ✅
- 24h/35h = 69% ✅
- **Résultat** : Éligible, fraction 31%, montant RP ~313€/mois

### Exemple 2 : Multi-employeurs (particuliers)
- Née en 1964 (61 ans)
- 172 trimestres ✅
- Employeur 1 : 18h/40h = 45%
- Employeur 2 : 12h/40h = 30%
- Total : 75% ✅
- **Résultat** : Éligible, fraction 25%

### Exemple 3 : Assistante maternelle
- Née en 1966 (58 ans) ❌
- 158 trimestres ✅
- Garde 3 enfants : quotité 71% ✅
- **Résultat** : Non éligible (âge insuffisant jusqu'à 61 ans)

---

## 🎯 CONTRÔLES IMPLÉMENTÉS

| ID | Contrôle | Type |
|---|---|---|
| **RP_C01** | Âge >= Âge minimum RP | ERREUR CRITIQUE |
| **RP_C02** | Trimestres >= 150 | ERREUR CRITIQUE |
| **RP_C03** | 40% <= Quotité <= 80% | ERREUR CRITIQUE |
| **RP_C04** | SAM > 0 | ERREUR CRITIQUE |
| **RP_C05** | 37,5% <= Taux <= 50% | ERREUR CRITIQUE |
| **RP_C06** | Trimestres RG > 0 | ERREUR CRITIQUE |
| **RP_C07** | 20% <= Fraction <= 60% | ERREUR CRITIQUE |
| **RP_C08** | Modification période annuelle | AVERTISSEMENT |
| **RP_C09** | Questionnaire renvoyé | AVERTISSEMENT |
| **RP_C10** | Activité conforme | AVERTISSEMENT |
| **RP_C11** | Montant minimal respecté | VÉRIFICATION |
| **RP_C12** | Cumul après liquidation | INFORMATION |

---

## 🚀 PROCHAINES ÉTAPES

### Phase 1 (FAIT ✅)
- ✅ Éligibilité RP (3 conditions)
- ✅ Calcul fraction pension (40-80%)
- ✅ Multi-employeurs (depuis 2018)
- ✅ Pension provisoire et définitive
- ✅ Contrôles de cohérence

### Phase 2 (À FAIRE)
- ⏳ Calcul précis AGIRC-ARRCO pendant RP
- ⏳ Calcul IRCANTEC pendant RP
- ⏳ Simulation cotisations temps plein (L.241-3-1)
- ⏳ Intégration LURA (liquidation unique)

### Phase 3 (À PRÉVOIR)
- ⏳ Agents non-titulaires fonction publique
- ⏳ Assistantes maternelles (détails mensualisation/annualisation)
- ⏳ Retraite progressive non-salariés (artisans, commerçants, libérales)

---

## 📚 SOURCES RÉGLEMENTAIRES

### Lois
- **CSS L.351-15 et L.351-16** : Base légale RP
- **Loi 2014-40 du 20/01/2014** : Amélioration RP (décote 25%)
- **Loi 2016-1827 du 23/12/2016** : Extension multi-employeurs

### Décrets
- **Décret 2014-1513 du 16/12/2014** : Barème fraction simplifié
- **Décret 2017-1645 du 30/11/2017** : Multi-employeurs et modalités

### Circulaires CNAV
- **Circulaire 2018-31 du 21/12/2018** : Circulaire de référence consolidée

---

## 📄 DOCUMENTS UPLOADÉS UTILISÉS

1. **circulaire_cnav_2018_31_21122018.pdf** :
   - Conditions d'éligibilité (âge, durée, quotité)
   - Multi-employeurs (nouveauté 2018)
   - Cas particuliers (particuliers employeurs, assistantes maternelles)
   - Service, modification, suspension, suppression
   - Liquidation définitive
   - Pension d'invalidité

2. **retraite_progressive_nouvelles_conditions.pdf** :
   - Améliorations depuis 2015 (âge 60 ans, 150 trimestres)
   - Régimes spéciaux inclus (depuis 2015)
   - Cotisations sur temps plein (option L.241-3-1)
   - Régimes complémentaires (ARRCO/AGIRC)

---

## ⚠️ LIMITATIONS PHASE 1

### Exclusions
- ❌ Retraite progressive des non-salariés
- ❌ Fonctionnaires titulaires (régime spécial)
- ❌ Calcul détaillé AGIRC-ARRCO pendant RP
- ❌ Calcul IRCANTEC pendant RP

### Simplifications
- Estimation salaire temps partiel (approximation 2000€ temps complet)
- Pas de calcul précis des régimes complémentaires pendant RP
- LURA (liquidation unique) non intégré

---

## 💡 POINTS CLÉS À RETENIR

### Pour les consultants
1. **3 conditions obligatoires** : Âge (60 ans min), Durée (150 trim), Quotité (40-80%)
2. **Multi-employeurs autorisé** depuis 2018 → additionner les quotités
3. **Décote plafonnée à 25%** (au lieu de 37,5% en droit commun)
4. **Pension provisoire** → recalculée en définitive avec tous les droits acquis
5. **Règle du montant minimal** : Pension définitive >= Pension provisoire revalorisée

### Pour les clients
1. **Transition progressive** vers la retraite dès 60 ans
2. **Revenus combinés** : Salaire temps partiel + fraction de pension
3. **Amélioration des droits** : Nouveaux trimestres et points pendant la RP
4. **Questionnaire annuel** obligatoire (risque suspension si non renvoyé)
5. **Vigilance quotité** : Rester entre 40% et 80% (sinon suppression)

---

## 📊 STATISTIQUES

### Volumétrie des fichiers
- **SKILL.md** : ~80 Ko, 1200 lignes
- **JSON** : ~20 Ko, 500 lignes
- **Python** : ~25 Ko, 800 lignes
- **TOTAL** : ~125 Ko, 2500 lignes de code/doc

### Tokens estimés
- Lecture SKILL.md : ~15 000 tokens
- Utilisation api_handler : ~500 tokens par appel

---

## 🎉 CONCLUSION

Le skill **Retraite Progressive** est maintenant **COMPLET** au format "classe mondiale" :

✅ Documentation exhaustive (SKILL.md)  
✅ Règles structurées JSON (retraite_progressive_regles.json)  
✅ Calculs automatisés Python (calcul_retraite_progressive.py)  
✅ Intégration N8N via api_handler  
✅ Contrôles de cohérence (RP_C01 à RP_C12)  
✅ Exemples pratiques  
✅ Cas particuliers couverts  
✅ Alertes métier  

**Prêt pour la production !** 🚀

---

**Contact** : Jeff - EOR  
**Date** : 10/11/2025  
**Version** : 1.0
