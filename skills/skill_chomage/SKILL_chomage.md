# SKILL : CHÔMAGE

## 🎯 CONTEXTE D'UTILISATION

### Quand utiliser ce skill ?
- ✅ Client avec périodes de chômage dans sa carrière.
- ✅ Vérification de trimestres assimilés, carrière longue ou points complémentaire.
- ✅ Contrôle d'années lacunaires dans le RIS ou le relevé Agirc-Arrco.

## 📋 CATÉGORIES DE PÉRIODES - CLASSIFICATION

### 🟢 Chômage indemnisé
- 1 trimestre assimilé pour 50 jours.
- Maximum 4 trimestres par année civile.
- Points complémentaire potentiels.
- Pas d'impact positif sur le SAM.

### 🟡 Chômage non indemnisé suite à une période indemnisée
- Validation possible sous conditions.
- Limite standard : 1 an.
- Exception senior : jusqu'à 5 ans si conditions d'âge et de carrière remplies.

### 🟠 Première période de chômage non indemnisé autonome
- Avant 2011 : plafond usuel de 4 trimestres.
- Depuis 2011 : plafond usuel de 6 trimestres.

### 🔴 Chômage non indemnisé non qualifié
- À ne pas retenir automatiquement.
- Contrôle manuel requis.

## 🔄 WORKFLOW CONSULTANT - ÉTAPES
1. Qualifier la période.
2. Contrôler dates et durée.
3. Mesurer l'impact retraite.
4. Préparer l'explication client.

## ⚠️ CONTRÔLES QUALITÉ
- CHO_C01 à CHO_C09 intégrés.
- Contrôles sur qualification, dates, plafonds, carrière longue et complémentaire.

## 🔗 UTILISATION API POUR N8N
Actions disponibles :
- `qualifier_periode`
- `calculer_trimestres`
- `analyser_impact`
- `estimer_points`

## 🔄 INTÉGRATION AVEC AUTRES SKILLS
- `skill_racl`
- `skill_complementaires`
- `skill_validation_autocontrole`
- `skill_analyse_releve`
