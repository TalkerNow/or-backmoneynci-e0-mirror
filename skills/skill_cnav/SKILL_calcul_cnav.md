# SKILL : Calcul Pension CNAV (Régime Général)

**Version** : 1.0  
**Date** : 2025-01-10  
**Type** : Skill Calcul Régime (BRUT uniquement)

---

## 1. Contexte réglementaire

### 1.1 Cadre juridique

**Articles de référence :**
- Article L.351-1 CSS : Âge légal d'ouverture des droits
- Article L.351-8 CSS : Âge du taux plein automatique
- Article L.351-1 CSS : Formule de calcul de la pension
- Article R.351-29 CSS : Salaire annuel moyen (SAM)
- Article L.351-1-3 CSS : Durée d'assurance requise

**Circulaires CNAV :**
- Circulaire CNAV 2024-25 du 01/08/2024 : Âges légaux et durées d'assurance
- Circulaire CNAV 2024-39 du 23/12/2024 : Revalorisation des salaires

### 1.2 Principe de calcul CNAV

**Citation réglementaire (Article L.351-1 CSS) :**
> "Le montant de la pension de vieillesse est déterminé en multipliant le salaire ou revenu annuel moyen par le taux et par le rapport entre la durée d'assurance au régime général et la durée de référence."

**Formule officielle :**
```
Pension annuelle = SAM × Taux × (Trimestres cotisés RG / Trimestres requis)
```

### 1.3 Périmètre de ce skill

**✅ Ce skill calcule :**
- Pension BRUTE de base CNAV
- Taux de liquidation standard (50% taux plein)
- Coefficient de proratisation
- Montants annuel et mensuel BRUTS

**❌ Ce skill NE calcule PAS :**
- Scénarios de départ (âges différents)
- Décote/surcote complexe (gérée par skills métier)
- Carrière longue (skill RACL)
- Cumul emploi retraite (skill CER)
- Minimum contributif (MICO)
- Majorations pour enfants

**→ Les transformations selon choix client = Skills métier (RACL, CER, etc.)**

---

## 2. Composants du système

### 2.1 Salaire Annuel Moyen (SAM)

**Définition (Article R.351-29 CSS) :**
Le SAM est la moyenne des salaires revalorisés des **25 meilleures années** de la carrière, dans la limite du plafond de la Sécurité sociale.

**Calcul :**
```
SAM = Somme(25 meilleurs salaires annuels revalorisés) / 25
```

**Plafond SS 2025** : 47 100 €

**Note importante :**
Le SAM est généralement **fourni** par le relevé de carrière ou calculé en amont. Ce skill utilise le SAM fourni en paramètre.

### 2.2 Taux de liquidation

**Taux plein (50%) :**
Obtenu si l'une des conditions est remplie :
- Durée d'assurance requise atteinte (tous régimes)
- Âge du taux plein automatique atteint (67 ans pour génération 1955+)

**Taux minoré (décote) :**
Si départ avant taux plein, application d'une décote de **1,25% par trimestre manquant** (dans la limite de 20 trimestres).

**Formule décote :**
```
Taux = 50% - (Trimestres manquants × 1,25%)
Minimum : 37,5% (décote maximale : 20 trimestres)
```

**Taux majoré (surcote) :**
Si poursuite activité après taux plein, majoration de **1,25% par trimestre supplémentaire**.

**Formule surcote :**
```
Taux = 50% + (Trimestres supplémentaires × 1,25%)
Pas de maximum
```

### 2.3 Coefficient de proratisation

**Définition :**
Rapport entre les trimestres cotisés au régime général et la durée de référence de la génération.

**Formule :**
```
Prorata = Trimestres cotisés RG / Durée requise génération
Maximum : 1,00 (100%)
```

**Durées requises par génération :**

| Année de naissance | Trimestres requis | Années |
|-------------------|-------------------|---------|
| 1958-1960 | 167 | 41 ans 9 mois |
| Janv-Août 1961 | 168 | 42 ans |
| Sept-Déc 1961, 1962 | 169 | 42 ans 3 mois |
| 1963 | 170 | 42 ans 6 mois |
| 1964 | 171 | 42 ans 9 mois |
| 1965 et après | 172 | 43 ans |

---

## 3. Formule complète de calcul

### 3.1 Formule officielle

```
Pension annuelle BRUTE = SAM × Taux × Prorata

Où :
- SAM = Salaire Annuel Moyen (€)
- Taux = Taux de liquidation (%)
- Prorata = Trimestres RG / Trimestres requis
```

### 3.2 Exemple de calcul

**Données client :**
- Date de naissance : 15/03/1965
- SAM : 42 000 €
- Trimestres validés tous régimes : 173
- Trimestres cotisés RG : 165
- Âge de départ : 63 ans (756 mois)

**Durée requise génération 1965 :** 172 trimestres

**Taux :**
- Trimestres validés (173) ≥ Requis (172) → **Taux plein 50%**

**Prorata :**
```
165 trimestres RG / 172 requis = 0,9593 (95,93%)
```

**Calcul pension :**
```
Pension annuelle = 42 000 € × 50% × 0,9593
                 = 42 000 € × 0,50 × 0,9593
                 = 20 145,30 €

Pension mensuelle = 20 145,30 € / 12 = 1 678,78 €
```

**Résultat BRUT :**
- Pension annuelle BRUTE : **20 145,30 €**
- Pension mensuelle BRUTE : **1 678,78 €**

**Note :** Montants AVANT prélèvements sociaux (CSG/CRDS : 9,2% en 2025)

---

## 4. Valeurs réglementaires 2025

### 4.1 Âges de départ

| Élément | Valeur |
|---------|--------|
| Âge légal (génération 1955+) | 62 ans |
| Âge taux plein auto (génération 1955+) | 67 ans |

### 4.2 Plafonds et valeurs

| Élément | Valeur 2025 |
|---------|-------------|
| Plafond Sécurité Sociale annuel | 47 100 € |
| Plafond SS mensuel | 3 925 € |
| SMIC horaire brut | 11,88 € |
| SMIC mensuel brut (151,67h) | 1 801,84 € |

### 4.3 Taux et coefficients

| Élément | Valeur |
|---------|--------|
| Taux plein | 50,00% |
| Taux décote par trimestre | 1,25% |
| Taux surcote par trimestre | 1,25% |
| Décote maximale | 12,50% (20 trimestres) |
| Taux minimal | 37,50% |

### 4.4 Prélèvements sociaux 2025

| Prélèvement | Taux |
|-------------|------|
| CSG | 8,3% |
| CRDS | 0,5% |
| Casa | 0,3% |
| Maladie | 0,1% |
| **TOTAL** | **9,2%** |

---

## 5. Contrôles de cohérence

### CNAV_C01 - SAM supérieur au plafond

**Type :** 🟠 ALERTE ORANGE

**Condition :**
```
SAM > Plafond_SS_annuel_2025
```

**Règle réglementaire :**
Le SAM ne peut pas dépasser le plafond SS de l'année de liquidation.

**Message d'alerte :**
"SAM fourni ({sam}€) supérieur au plafond SS 2025 (47 100€). Vérifier les données ou plafonner le SAM."

**Action corrective :**
- Vérifier le SAM fourni
- Appliquer le plafonnement si nécessaire : SAM = MIN(SAM, 47 100€)

---

### CNAV_C02 - Prorata supérieur à 100%

**Type :** 🟠 ALERTE ORANGE

**Condition :**
```
(Trimestres_cotisés_RG / Trimestres_requis) > 1,00
```

**Règle réglementaire :**
Le coefficient de proratisation est plafonné à 100%.

**Message d'alerte :**
"Prorata calculé : {prorata}% > 100%. Application du plafonnement à 100%."

**Action corrective :**
- Prorata = MIN(calculé, 1,00)

---

### CNAV_C03 - Trimestres cotisés RG > Trimestres validés tous régimes

**Type :** 🔴 ERREUR CRITIQUE

**Condition :**
```
Trimestres_cotisés_RG > Trimestres_validés_tous_regimes
```

**Règle réglementaire :**
Incohérence logique : les trimestres cotisés dans un régime ne peuvent pas dépasser le total validé.

**Message d'erreur :**
"Incohérence : Trimestres RG ({rg}) > Trimestres totaux ({total}). Vérifier les données."

**Action corrective :**
- Bloquer le calcul
- Demander vérification des données

---

### CNAV_C04 - Taux calculé hors limites

**Type :** 🔴 ERREUR CRITIQUE

**Condition :**
```
Taux_calcule < 37,5% OU Taux_calcule > 75%
```

**Règle réglementaire :**
Le taux de liquidation est compris entre 37,5% (décote max) et 75% (surcote importante).

**Message d'erreur :**
"Taux calculé ({taux}%) hors limites réglementaires [37,5% - 75%]. Vérifier les paramètres."

**Action corrective :**
- Bloquer le calcul
- Vérifier les paramètres de calcul

---

### CNAV_C05 - SAM inférieur au SMIC annuel

**Type :** 🟡 ALERTE JAUNE

**Condition :**
```
SAM < (SMIC_mensuel × 12)
```

**Règle réglementaire :**
Un SAM inférieur au SMIC annuel est inhabituel et peut indiquer une carrière incomplète ou des erreurs.

**Message d'alerte :**
"SAM ({sam}€) inférieur au SMIC annuel ({smic_annuel}€). Vérifier la carrière."

**Action corrective :**
- Alerter le consultant
- Vérifier si carrière complète
- Possibilité de MICO (Minimum Contributif)

---

### CNAV_C06 - Durée requise génération non trouvée

**Type :** 🔴 ERREUR CRITIQUE

**Condition :**
```
Annee_naissance < 1958 OU Annee_naissance > 2030
```

**Règle réglementaire :**
Les durées d'assurance requises sont définies pour les générations 1958 à 2030.

**Message d'erreur :**
"Génération {annee} hors périmètre [1958-2030]. Impossible de déterminer la durée requise."

**Action corrective :**
- Bloquer le calcul
- Étendre les barèmes si nécessaire

---

## 6. Signaux d'alerte

### 🔴 ROUGE - Blocage calcul

**CNAV_A01 - Données incohérentes**
- **Déclencheur :** Trimestres RG > Trimestres totaux
- **Message :** "Données incohérentes : vérification requise"
- **Action :** Bloquer calcul / Demander vérification

**CNAV_A02 - Taux hors limites**
- **Déclencheur :** Taux < 37,5% ou > 75%
- **Message :** "Taux calculé hors limites réglementaires"
- **Action :** Bloquer calcul / Vérifier paramètres

**CNAV_A03 - Génération non supportée**
- **Déclencheur :** Année naissance < 1958 ou > 2030
- **Message :** "Génération hors périmètre"
- **Action :** Bloquer calcul / Étendre barèmes

---

### 🟠 ORANGE - Attention requise

**CNAV_A04 - SAM plafonné**
- **Déclencheur :** SAM fourni > Plafond SS
- **Message :** "SAM plafonné au plafond SS 2025"
- **Action :** Appliquer plafonnement / Informer

**CNAV_A05 - Prorata plafonné**
- **Déclencheur :** Prorata calculé > 100%
- **Message :** "Prorata plafonné à 100%"
- **Action :** Appliquer plafonnement / Informer

---

### 🟡 JAUNE - Point de vigilance

**CNAV_A06 - SAM faible**
- **Déclencheur :** SAM < SMIC annuel
- **Message :** "SAM inférieur au SMIC : vérifier carrière"
- **Action :** Alerter consultant / Vérifier MICO

**CNAV_A07 - Prorata faible**
- **Déclencheur :** Prorata < 0,50 (50%)
- **Message :** "Prorata faible : carrière courte au RG"
- **Action :** Vérifier poly-pensionné

**CNAV_A08 - Pension faible**
- **Déclencheur :** Pension mensuelle < 500€
- **Message :** "Pension CNAV faible : éligibilité MICO à vérifier"
- **Action :** Orienter vers calcul MICO

---

## 7. Exemples pratiques

### Exemple 1 : Cas standard taux plein

**Données :**
- Né : 20/04/1965
- SAM : 42 000 €
- Trimestres validés : 173
- Trimestres RG : 165
- Âge départ : 63 ans

**Calculs :**
- Durée requise 1965 : 172 trimestres
- Taux : 173 ≥ 172 → **50%**
- Prorata : 165 / 172 = **0,9593**

**Résultat :**
```
Pension annuelle = 42 000 × 0,50 × 0,9593 = 20 145,30 €
Pension mensuelle = 20 145,30 / 12 = 1 678,78 €
```

---

### Exemple 2 : Cas avec décote

**Données :**
- Né : 15/03/1965
- SAM : 38 000 €
- Trimestres validés : 164
- Trimestres RG : 160
- Âge départ : 62 ans

**Calculs :**
- Durée requise 1965 : 172 trimestres
- Trimestres manquants : 172 - 164 = **8 trimestres**
- Décote : 8 × 1,25% = **10%**
- Taux : 50% - 10% = **40%**
- Prorata : 160 / 172 = **0,9302**

**Résultat :**
```
Pension annuelle = 38 000 × 0,40 × 0,9302 = 14 139,04 €
Pension mensuelle = 14 139,04 / 12 = 1 178,25 €
```

---

### Exemple 3 : Cas avec surcote

**Données :**
- Né : 10/06/1963
- SAM : 45 000 €
- Trimestres validés : 178
- Trimestres RG : 170
- Âge départ : 64 ans

**Calculs :**
- Durée requise 1963 : 170 trimestres
- Trimestres excédentaires : 178 - 170 = **8 trimestres**
- Surcote : 8 × 1,25% = **10%**
- Taux : 50% + 10% = **60%**
- Prorata : 170 / 170 = **1,00**

**Résultat :**
```
Pension annuelle = 45 000 × 0,60 × 1,00 = 27 000,00 €
Pension mensuelle = 27 000,00 / 12 = 2 250,00 €
```

---

## 8. Utilisation avec Excel

### 8.1 Fichier Excel associé

**Nom du fichier :** `CNAV_baremes_calculs.xlsx`

**Structure :**
- **Feuille 1 : Barèmes** - Données historiques et valeurs 2025
- **Feuille 2 : Calculateur** - Formules de calcul BRUT
- **Feuille 3 : Alertes** - Statut MAJ et alertes

### 8.2 Workflow Python ↔ Excel

```
1. Python charge Excel
2. Python vérifie alertes (Feuille 3)
3. Python remplit INPUT (Feuille 2)
4. Excel recalcule automatiquement (formules)
5. Python lit OUTPUT (Feuille 2)
6. Python retourne JSON
```

### 8.3 Formules Excel principales

**Feuille 2 : Calculateur**

```excel
# INPUT (rempli par Python)
B2: Date naissance
B3: SAM
B4: Trimestres validés tous régimes
B5: Trimestres cotisés RG
B6: Âge départ (mois)

# CALCULS
B10: Année naissance = ANNEE(B2)
B11: Durée requise = RECHERCHEV(B10, Baremes!A:B, 2, 0)
B12: Trimestres manquants = MAX(0, B11 - B4)
B13: Taux base = 50%
B14: Décote = MIN(B12 * 1.25%, 12.5%)
B15: Taux final = B13 - B14
B16: Prorata = MIN(B5 / B11, 1)

# OUTPUT
B20: Pension annuelle = B3 * B15 * B16
B21: Pension mensuelle = B20 / 12
```

---

## 9. API Handler - Point d'entrée N8N

### 9.1 Signature

```python
def api_handler(params: Dict) -> Dict
```

### 9.2 Paramètres attendus

```json
{
    "date_naissance": "15/03/1965",
    "sam": 42000.0,
    "trimestres_valides_tous_regimes": 173,
    "trimestres_cotises_rg": 165,
    "age_depart_mois": 756
}
```

### 9.3 Retour JSON

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
        "duree_requise": 172,
        "trimestres_rg": 165
    },
    "controles": [...],
    "alertes": [...],
    "tokens_estimes": 800
}
```

---

## 10. Sources documentaires

### 10.1 Textes législatifs

- Article L.351-1 CSS : Formule de calcul pension
- Article L.351-1-3 CSS : Durée d'assurance requise
- Article L.351-8 CSS : Âge taux plein automatique
- Article R.351-29 CSS : Salaire annuel moyen

### 10.2 Circulaires CNAV

- Circulaire CNAV 2024-25 du 01/08/2024 : Âges légaux et durées
- Circulaire CNAV 2024-39 du 23/12/2024 : Revalorisation salaires
- Circulaire CNAV 2025-XX : Plafond SS 2025

### 10.3 Documentation technique

- Barème Excel : `CNAV_baremes_calculs.xlsx`
- Script Python : `calcul_cnav.py`
- Règles JSON : `calcul_cnav_regles.json`

---

**FIN DU SKILL CALCUL CNAV**

*Document créé le : 2025-01-10*  
*Version : 1.0*  
*Type : Skill Calcul Régime - BRUT uniquement*  
*Conformité : ARCHITECTURE_SYSTEME_EOR.md*
