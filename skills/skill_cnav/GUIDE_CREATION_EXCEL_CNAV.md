# GUIDE DE CRÉATION - CNAV_baremes_calculs.xlsx

**Version** : 1.0  
**Date** : 2025-01-10  
**Régime** : CNAV (Régime Général)

---

## 📊 STRUCTURE DU FICHIER

Le fichier Excel doit contenir **3 feuilles** :
1. **Baremes** - Données historiques et valeurs 2025
2. **Calculateur** - Formules de calcul BRUT
3. **Alertes** - Statut MAJ et alertes

---

## FEUILLE 1 : Baremes

### Structure

| A | B | C | D | E | F |
|---|---|---|---|---|---|
| **Année** | **Plafond SS** | **SMIC horaire** | **SMIC mensuel** | **Durée 1965+** | **Taux plein** |
| 2020 | 41 136 | 10,15 | 1 539,42 | 172 | 50,0% |
| 2021 | 41 136 | 10,25 | 1 554,58 | 172 | 50,0% |
| 2022 | 41 136 | 10,57 | 1 603,12 | 172 | 50,0% |
| 2023 | 43 992 | 11,27 | 1 709,28 | 172 | 50,0% |
| 2024 | 46 368 | 11,65 | 1 766,92 | 172 | 50,0% |
| **2025** | **47 100** | **11,88** | **1 801,84** | **172** | **50,0%** |

### Durées par génération (tableau séparé)

| **Génération** | **Trimestres requis** |
|---|---|
| 1958-1960 | 167 |
| 1961 (Jan-Août) | 168 |
| 1961 (Sept-Déc), 1962 | 169 |
| 1963 | 170 |
| 1964 | 171 |
| 1965 et après | 172 |

**Position** : Cellules A10:B16

---

## FEUILLE 2 : Calculateur

### Section INPUT (remplie par Python)

| Cellule | Libellé | Valeur (exemple) |
|---------|---------|------------------|
| **A2** | Date de naissance | (libellé) |
| **B2** | | 15/03/1965 |
| **A3** | SAM (€) | (libellé) |
| **B3** | | 42 000 |
| **A4** | Trimestres validés (tous régimes) | (libellé) |
| **B4** | | 173 |
| **A5** | Trimestres cotisés RG | (libellé) |
| **B5** | | 165 |
| **A6** | Âge départ (mois) | (libellé) |
| **B6** | | 756 |

### Section CALCULS (formules Excel)

| Cellule | Libellé | Formule Excel |
|---------|---------|---------------|
| **A10** | Année naissance | (libellé) |
| **B10** | | `=ANNEE(B2)` |
| **A11** | Durée requise | (libellé) |
| **B11** | | `=SI(B10>=1965, 172, SI(B10=1964, 171, SI(B10=1963, 170, SI(B10>=1961, 169, 167))))` |
| **A12** | Trimestres manquants | (libellé) |
| **B12** | | `=MAX(0, B11-B4)` |
| **A13** | Taux de base | (libellé) |
| **B13** | | `=50%` |
| **A14** | Décote (%) | (libellé) |
| **B14** | | `=MIN(B12*1,25%, 12,5%)` |
| **A15** | Taux final (%) | (libellé) |
| **B15** | | `=MAX(B13-B14, 37,5%)` |
| **A16** | Prorata | (libellé) |
| **B16** | | `=MIN(B5/B11, 1)` |

### Section OUTPUT (résultats)

| Cellule | Libellé | Formule Excel |
|---------|---------|---------------|
| **A20** | **PENSION ANNUELLE BRUTE** | (libellé en gras) |
| **B20** | | `=B3*B15*B16` |
| **A21** | **PENSION MENSUELLE BRUTE** | (libellé en gras) |
| **B21** | | `=B20/12` |

### Formattage

- **Cellules B3** : Format nombre, 2 décimales, séparateur milliers
- **Cellules B13, B14, B15** : Format pourcentage, 2 décimales
- **Cellule B16** : Format nombre, 4 décimales
- **Cellules B20, B21** : Format nombre, 2 décimales, séparateur milliers
- **Lignes 20-21** : Fond gris clair, police gras

---

## FEUILLE 3 : Alertes

### Structure

| Cellule | Libellé | Valeur |
|---------|---------|--------|
| **A1** | Dernière MAJ | (libellé) |
| **B1** | | 15/01/2025 |
| **A2** | Statut | (libellé) |
| **B2** | | À JOUR |
| **A3** | Prochaine MAJ attendue | (libellé) |
| **B3** | | 15/07/2025 |
| **A5** | **Alertes actives** | (libellé gras) |
| **B5** | | Aucune |
| **A7** | **Historique modifications** | (libellé gras) |

### Historique (à partir de A8)

| Date | Modification | Auteur |
|------|--------------|--------|
| 15/01/2025 | Valeurs 2025 | Jeff |
| 01/11/2024 | Création fichier | Jeff |

### Règles de validation B2

Valider que B2 contient uniquement :
- "À JOUR"
- "ATTENTION"
- "OBSOLÈTE"

### Formattage conditionnel B2

```
SI B2 = "À JOUR" → Fond VERT, texte blanc
SI B2 = "ATTENTION" → Fond ORANGE, texte blanc
SI B2 = "OBSOLÈTE" → Fond ROUGE, texte blanc
```

---

## 🎨 MISE EN FORME GÉNÉRALE

### Police

- **Police par défaut** : Arial 11pt
- **Titres sections** : Arial 12pt, gras
- **Libellés** : Arial 11pt, normal
- **Valeurs** : Arial 11pt, normal

### Couleurs

- **En-têtes** : Fond bleu marine (#20295B), texte blanc
- **Section OUTPUT** : Fond gris clair (#E4E5E6)
- **Alertes positives** : Fond vert (#4CAF50)
- **Alertes attention** : Fond orange (#FF9800)
- **Alertes critiques** : Fond rouge (#F44336)

### Largeurs colonnes

- **Colonne A** : 30 caractères (libellés)
- **Colonne B** : 15 caractères (valeurs)
- **Autres colonnes** : Auto-ajustement

### Bordures

- Toutes les cellules avec données : Bordure fine grise (#CCCCCC)
- Sections OUTPUT : Bordure épaisse

---

## 🔧 FORMULES EXCEL DÉTAILLÉES

### Formule B11 : Durée requise (longue version)

```excel
=SI(B10>=1965, 172,
   SI(B10=1964, 171,
     SI(B10=1963, 170,
       SI(ET(B10=1961, MOIS(B2)>=9), 169,
         SI(ET(B10=1961, MOIS(B2)<=8), 168,
           SI(B10=1962, 169,
             SI(ET(B10>=1958, B10<=1960), 167, 172)))))))
```

**Note** : Cette formule gère finement les mois pour 1961, sinon utiliser la version simplifiée ci-dessus.

### Formule B14 : Décote avec limite 20 trimestres

```excel
=MIN(B12*1,25%, 12,5%)
```

**Explication** :
- B12 = Trimestres manquants
- 1,25% par trimestre
- Maximum 20 trimestres = 12,5% de décote

### Formule B15 : Taux final avec minimum 37,5%

```excel
=MAX(B13-B14, 37,5%)
```

**Explication** :
- Taux de base (50%) - Décote
- Minimum légal : 37,5%

### Formule B16 : Prorata plafonné à 100%

```excel
=MIN(B5/B11, 1)
```

**Explication** :
- Trimestres RG / Durée requise
- Maximum 100%

### Formule B20 : Pension annuelle

```excel
=B3*B15*B16
```

**Explication** :
- SAM × Taux × Prorata
- Résultat en euros annuels

---

## ✅ VÉRIFICATION DU FICHIER

### Tests à effectuer

1. **Test cas standard** :
   - B2 : 15/03/1965
   - B3 : 42 000
   - B4 : 173
   - B5 : 165
   - B6 : 756
   - **Attendu B20** : 20 145,30 €
   - **Attendu B21** : 1 678,78 €

2. **Test décote** :
   - B2 : 15/03/1965
   - B3 : 38 000
   - B4 : 164
   - B5 : 160
   - B6 : 744
   - **Attendu** : Taux 40%, Pension ~14 139 €

3. **Test plafonnement prorata** :
   - B5 : 200 (> durée requise)
   - **Attendu B16** : 1,00 (plafonné)

4. **Test alertes** :
   - Modifier B2 Feuille 3 : "OBSOLÈTE"
   - Vérifier formattage conditionnel (fond rouge)

---

## 📝 PROTECTION DU FICHIER

### Cellules à protéger

- **Feuille Baremes** : Toutes les cellules (en lecture seule sauf mise à jour annuelle)
- **Feuille Calculateur** :
  - Protéger : A1:A30, formules B10:B16, B20:B21
  - Déprotéger : INPUT B2:B6 (pour Python)
- **Feuille Alertes** :
  - Protéger : Toutes sauf B1, B2 (pour mise à jour manuelle)

### Mot de passe

Appliquer mot de passe si nécessaire (coordination avec équipe dev).

---

## 🔄 PROCÉDURE MAJ ANNUELLE

### Checklist mise à jour 2026

1. **Feuille Baremes** :
   - [ ] Ajouter ligne 2026 avec nouvelles valeurs
   - [ ] Plafond SS 2026
   - [ ] SMIC horaire 2026
   - [ ] SMIC mensuel 2026
   - [ ] Durée requise (si changement réforme)

2. **Feuille Alertes** :
   - [ ] Mettre à jour B1 (nouvelle date MAJ)
   - [ ] Vérifier B2 = "À JOUR"
   - [ ] Mettre à jour B3 (prochaine MAJ attendue)
   - [ ] Ajouter ligne historique

3. **Tests** :
   - [ ] Tester les 3 cas standards
   - [ ] Vérifier formules toujours valides
   - [ ] Vérifier avec script Python

---

## 💻 SCRIPT DE GÉNÉRATION PYTHON

### Script pour créer l'Excel automatiquement

```python
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter

def creer_excel_cnav():
    """Crée le fichier CNAV_baremes_calculs.xlsx"""
    
    wb = openpyxl.Workbook()
    
    # Supprimer feuille par défaut
    wb.remove(wb.active)
    
    # FEUILLE 1 : Baremes
    ws_baremes = wb.create_sheet("Baremes")
    
    # En-têtes
    headers = ["Année", "Plafond SS", "SMIC horaire", "SMIC mensuel", "Durée 1965+", "Taux plein"]
    for col, header in enumerate(headers, start=1):
        cell = ws_baremes.cell(1, col)
        cell.value = header
        cell.font = Font(bold=True, color="FFFFFF")
        cell.fill = PatternFill(start_color="20295B", end_color="20295B", fill_type="solid")
        cell.alignment = Alignment(horizontal="center")
    
    # Données 2020-2025
    data = [
        [2020, 41136, 10.15, 1539.42, 172, 0.50],
        [2021, 41136, 10.25, 1554.58, 172, 0.50],
        [2022, 41136, 10.57, 1603.12, 172, 0.50],
        [2023, 43992, 11.27, 1709.28, 172, 0.50],
        [2024, 46368, 11.65, 1766.92, 172, 0.50],
        [2025, 47100, 11.88, 1801.84, 172, 0.50]
    ]
    
    for row_idx, row_data in enumerate(data, start=2):
        for col_idx, value in enumerate(row_data, start=1):
            ws_baremes.cell(row_idx, col_idx, value)
    
    # Durées par génération
    ws_baremes.cell(10, 1, "Génération").font = Font(bold=True)
    ws_baremes.cell(10, 2, "Trimestres").font = Font(bold=True)
    
    durees_gen = [
        ["1958-1960", 167],
        ["1961 (Jan-Août)", 168],
        ["1961 (Sept-Déc), 1962", 169],
        ["1963", 170],
        ["1964", 171],
        ["1965 et après", 172]
    ]
    
    for row_idx, (gen, trim) in enumerate(durees_gen, start=11):
        ws_baremes.cell(row_idx, 1, gen)
        ws_baremes.cell(row_idx, 2, trim)
    
    # FEUILLE 2 : Calculateur
    ws_calc = wb.create_sheet("Calculateur")
    
    # INPUT
    ws_calc['A1'] = "INPUT (rempli par Python)"
    ws_calc['A1'].font = Font(bold=True, size=12)
    
    labels_input = [
        "Date de naissance",
        "SAM (€)",
        "Trimestres validés (tous régimes)",
        "Trimestres cotisés RG",
        "Âge départ (mois)"
    ]
    
    for row, label in enumerate(labels_input, start=2):
        ws_calc.cell(row, 1, label)
    
    # CALCULS
    ws_calc['A9'] = "CALCULS (formules)"
    ws_calc['A9'].font = Font(bold=True, size=12)
    
    ws_calc['A10'] = "Année naissance"
    ws_calc['B10'] = "=YEAR(B2)"
    
    ws_calc['A11'] = "Durée requise"
    ws_calc['B11'] = "=IF(B10>=1965, 172, IF(B10=1964, 171, IF(B10=1963, 170, IF(B10>=1961, 169, 167))))"
    
    ws_calc['A12'] = "Trimestres manquants"
    ws_calc['B12'] = "=MAX(0, B11-B4)"
    
    ws_calc['A13'] = "Taux de base"
    ws_calc['B13'] = 0.50
    ws_calc['B13'].number_format = '0.0%'
    
    ws_calc['A14'] = "Décote (%)"
    ws_calc['B14'] = "=MIN(B12*0.0125, 0.125)"
    ws_calc['B14'].number_format = '0.0%'
    
    ws_calc['A15'] = "Taux final (%)"
    ws_calc['B15'] = "=MAX(B13-B14, 0.375)"
    ws_calc['B15'].number_format = '0.0%'
    
    ws_calc['A16'] = "Prorata"
    ws_calc['B16'] = "=MIN(B5/B11, 1)"
    ws_calc['B16'].number_format = '0.0000'
    
    # OUTPUT
    ws_calc['A19'] = "OUTPUT (résultats)"
    ws_calc['A19'].font = Font(bold=True, size=12)
    
    ws_calc['A20'] = "PENSION ANNUELLE BRUTE"
    ws_calc['A20'].font = Font(bold=True)
    ws_calc['B20'] = "=B3*B15*B16"
    ws_calc['B20'].number_format = '#,##0.00'
    ws_calc['B20'].fill = PatternFill(start_color="E4E5E6", end_color="E4E5E6", fill_type="solid")
    
    ws_calc['A21'] = "PENSION MENSUELLE BRUTE"
    ws_calc['A21'].font = Font(bold=True)
    ws_calc['B21'] = "=B20/12"
    ws_calc['B21'].number_format = '#,##0.00'
    ws_calc['B21'].fill = PatternFill(start_color="E4E5E6", end_color="E4E5E6", fill_type="solid")
    
    # FEUILLE 3 : Alertes
    ws_alertes = wb.create_sheet("Alertes")
    
    ws_alertes['A1'] = "Dernière MAJ"
    ws_alertes['B1'] = "15/01/2025"
    
    ws_alertes['A2'] = "Statut"
    ws_alertes['B2'] = "À JOUR"
    ws_alertes['B2'].fill = PatternFill(start_color="4CAF50", end_color="4CAF50", fill_type="solid")
    ws_alertes['B2'].font = Font(color="FFFFFF", bold=True)
    
    ws_alertes['A3'] = "Prochaine MAJ attendue"
    ws_alertes['B3'] = "15/07/2025"
    
    ws_alertes['A5'] = "Alertes actives"
    ws_alertes['A5'].font = Font(bold=True)
    ws_alertes['B5'] = "Aucune"
    
    ws_alertes['A7'] = "Historique modifications"
    ws_alertes['A7'].font = Font(bold=True)
    
    ws_alertes['A8'] = "Date"
    ws_alertes['B8'] = "Modification"
    ws_alertes['C8'] = "Auteur"
    
    ws_alertes['A9'] = "15/01/2025"
    ws_alertes['B9'] = "Création fichier + Valeurs 2025"
    ws_alertes['C9'] = "Jeff"
    
    # Sauvegarder
    wb.save('CNAV_baremes_calculs.xlsx')
    print("Fichier CNAV_baremes_calculs.xlsx créé avec succès !")

if __name__ == "__main__":
    creer_excel_cnav()
```

### Utilisation

```bash
python script_creation_excel_cnav.py
```

---

## 📞 SUPPORT

Pour toute question sur la création du fichier Excel :
1. Consulter ce guide
2. Tester avec les 3 cas standards
3. Vérifier avec le script Python calcul_cnav.py

---

**Document créé le** : 2025-01-10  
**Version** : 1.0  
**Auteur** : EOR - Expertise Optimisation des Retraites
