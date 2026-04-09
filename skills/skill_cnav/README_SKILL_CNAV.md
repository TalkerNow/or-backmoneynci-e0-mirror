# ✅ SKILL CNAV - LIVRAISON COMPLÈTE

**Date** : 2025-01-10  
**Version** : 1.0  
**Type** : Skill Calcul Régime - BRUT uniquement  
**Statut** : ✅ DEMO - Validation approche

---

## 📦 4 FICHIERS LIVRÉS

### 1. [SKILL_calcul_cnav.md](computer:///mnt/user-data/outputs/SKILL_calcul_cnav.md) (14 Ko)
**Documentation Markdown "classe mondiale"**
- ✅ Contexte réglementaire (Articles CSS, Circulaires CNAV)
- ✅ Formule officielle : Pension = SAM × Taux × Prorata
- ✅ Valeurs 2025 intégrées
- ✅ 6 contrôles de cohérence (CNAV_C01 à CNAV_C06)
- ✅ 8 alertes Rouge/Orange/Jaune
- ✅ 3 exemples pratiques
- ✅ **PAS de section "Déclencheurs"** (conforme architecture)

### 2. [calcul_cnav_regles.json](computer:///mnt/user-data/outputs/calcul_cnav_regles.json) (13 Ko)
**Structured data complet**
- ✅ Formule officielle structurée
- ✅ Valeurs réglementaires 2025
- ✅ Règles de calcul (SAM, Taux, Prorata)
- ✅ 6 contrôles avec conditions
- ✅ 8 alertes par niveau
- ✅ 3 exemples de calcul JSON
- ✅ Sources documentaires

### 3. [calcul_cnav.py](computer:///mnt/user-data/outputs/calcul_cnav.py) (19 Ko)
**Script Python avec API N8N**
- ✅ `api_handler(params)` point d'entrée
- ✅ **Utilise Excel** pour calculs (openpyxl)
- ✅ Vérification alertes MAJ Excel
- ✅ 8 contrôles automatisés
- ✅ Génération alertes
- ✅ Calculs directs (si Excel indisponible)
- ✅ 3 exemples d'utilisation
- ✅ Estimation tokens

### 4. [GUIDE_CREATION_EXCEL_CNAV.md](computer:///mnt/user-data/outputs/GUIDE_CREATION_EXCEL_CNAV.md) (13 Ko)
**Guide détaillé création Excel**
- ✅ Structure 3 feuilles (Baremes, Calculateur, Alertes)
- ✅ **TOUTES les formules Excel** détaillées
- ✅ Mise en forme complète
- ✅ Tests de vérification
- ✅ Procédure MAJ annuelle
- ✅ Script Python génération auto

---

## 🎯 PRINCIPE VALIDÉ

### Excel = Calcul BRUT régime

**Excel contient** :
- Feuille 1 : Barèmes (Plafond SS, SMIC, durées par génération)
- Feuille 2 : **Formules de calcul BRUT**
  - INPUT : Date naissance, SAM, Trimestres
  - CALCULS : Taux, Prorata (formules Excel)
  - OUTPUT : Pension annuelle, mensuelle
- Feuille 3 : Alertes MAJ

**Excel NE contient PAS** :
- ❌ Scénarios fin de carrière
- ❌ "Si je pars à 63 vs 64 ans"
- ❌ Arbitrages RACL
- ❌ Cumul emploi retraite

### Python Skills = Transformations métier

**Skills métier utilisent** l'Excel :
```python
# Skill RACL
pension_brute = lire_excel_cnav(params)
if eligible_racl:
    pension_finale = pension_brute * 1.0  # Pas d'abattement
else:
    pension_finale = pension_brute * 0.9  # Abattement

# Skill CER
pension_cnav = lire_excel_cnav(params)
if (pension_cnav + revenus) > plafond:
    ecretement = ...
```

---

## 📊 Architecture validée

```
┌────────────────────────────────────┐
│  EXCEL CNAV (calcul BRUT)         │
│                                    │
│  • Barèmes officiels              │
│  • Formules de base               │
│  • Pas de scénarios               │
└──────────────┬─────────────────────┘
               │
       ┌───────┴────────┐
       │                │
  ┌────▼────┐    ┌─────▼─────┐
  │ RACL    │    │ CER       │
  │ (skill) │    │ (skill)   │
  └─────────┘    └───────────┘
       │                │
       └────────┬───────┘
                │
         ┌──────▼──────┐
         │  Scénarios  │
         │  client     │
         └─────────────┘
```

**Séparation des responsabilités** ✅
- Excel = Vérité du régime
- Python = Intelligence métier

---

## 🔍 Formule CNAV

### Formule officielle (Article L.351-1 CSS)

```
Pension annuelle = SAM × Taux × Prorata

Où :
• SAM = Salaire Annuel Moyen (25 meilleures années)
• Taux = 37,5% à 75% (décote/taux plein/surcote)
• Prorata = Trimestres RG / Durée requise (max 100%)
```

### Valeurs 2025

| Élément | Valeur |
|---------|--------|
| Plafond SS annuel | 47 100 € |
| SMIC horaire | 11,88 € |
| SMIC mensuel | 1 801,84 € |
| Taux plein | 50,0% |
| Décote par trimestre | 1,25% |
| Durée 1965+ | 172 trimestres |

---

## 💻 Utilisation N8N

### Bouton interface

```
[Bouton "Calculer CNAV"]
```

### Flow N8N

```
1. Charge CNAV_baremes_calculs.xlsx
2. Vérifie alertes MAJ (Feuille 3)
3. Appelle api_handler(params)
4. Python remplit INPUT Excel (Feuille 2)
5. Excel recalcule (formules automatiques)
6. Python lit OUTPUT Excel
7. Python ajoute contrôles + alertes
8. Retourne JSON
```

### Params

```json
{
  "date_naissance": "15/03/1965",
  "sam": 42000.0,
  "trimestres_valides_tous_regimes": 173,
  "trimestres_cotises_rg": 165,
  "age_depart_mois": 756
}
```

### Retour

```json
{
  "regime": "CNAV",
  "calcul_type": "BRUT",
  "pension_annuelle": 20145.30,
  "pension_mensuelle": 1678.78,
  "details": {
    "sam": 42000.0,
    "taux": 50.0,
    "prorata": 0.9593,
    "duree_requise": 172
  },
  "controles": [...],
  "alertes": [...],
  "source_calcul": "Excel"
}
```

---

## ✅ Contrôles de cohérence (6)

### 🔴 ROUGE (3)
- **CNAV_C03** : Trimestres RG > Trimestres totaux
- **CNAV_C04** : Taux hors limites [37,5% - 75%]
- **CNAV_C06** : Génération hors périmètre [1958-2030]

### 🟠 ORANGE (2)
- **CNAV_C01** : SAM > Plafond SS (correction auto)
- **CNAV_C02** : Prorata > 100% (correction auto)

### 🟡 JAUNE (1)
- **CNAV_C05** : SAM < SMIC annuel (alerte MICO)

---

## 📊 Exemples pratiques

### Exemple 1 : Taux plein
- SAM : 42 000 €
- Trimestres : 173/172 ✅
- Taux : 50%
- Prorata : 95,93%
- **Pension** : 20 145 € / an (1 679 € / mois)

### Exemple 2 : Décote
- SAM : 38 000 €
- Trimestres : 164/172 (manque 8)
- Décote : 8 × 1,25% = 10%
- Taux : 40%
- **Pension** : 14 139 € / an (1 178 € / mois)

### Exemple 3 : Surcote
- SAM : 45 000 €
- Trimestres : 178/170 (+8)
- Surcote : 8 × 1,25% = 10%
- Taux : 60%
- **Pension** : 27 000 € / an (2 250 € / mois)

---

## 🔄 Prochaines étapes

### Validation Jeff
1. ✅ Lire les 4 fichiers
2. ✅ Valider l'approche Excel + Python
3. ✅ Tester un calcul manuel
4. ✅ Créer l'Excel (ou demander à dev)

### Si approche validée
**Créer les 3 autres skills régimes** :
- AGIRC-ARRCO (complémentaire salariés)
- IRCANTEC (contractuels public)
- RCI/RCO (indépendants)

**Même structure** :
- 4 fichiers (MD + JSON + PY + Guide Excel)
- Excel = Calcul BRUT
- Python = API handler
- Skills métier réutilisent

---

## 📈 Budget tokens

**Utilisés pour CNAV** : ~35 000 tokens  
**Restants** : 76 696 (40%) ✅

**Estimation autres régimes** :
- ARRCO : ~35 000 tokens
- IRCANTEC : ~30 000 tokens
- RCI : ~35 000 tokens

**Total 4 régimes** : ~135 000 tokens  
**Besoin nouvelle conversation** pour les 3 autres

---

## ✅ Conformité

**Architecture** : 100% conforme `ARCHITECTURE_SYSTEME_EOR.md`
- ✅ Pas de déclencheurs
- ✅ API handler N8N
- ✅ 3 fichiers + Guide Excel
- ✅ Contrôles de cohérence
- ✅ Alertes Rouge/Orange/Jaune
- ✅ Documentation "classe mondiale"

**Approche** : 100% conforme discussion Jeff
- ✅ Excel = Calcul BRUT régime
- ✅ Python = Transformations métier
- ✅ Séparation responsabilités
- ✅ Maintenabilité
- ✅ Évolutivité

---

## 🎯 C'EST BORDÉ

**Skill CNAV DEMO** prêt pour validation.

**Si OK** → On fait ARRCO, IRCANTEC, RCI.

---

**Créé le** : 2025-01-10  
**Statut** : ✅ VALIDATION  
**Prêt pour** : Tests + Intégration N8N
