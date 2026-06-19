# Correction CRITIQUE : Coefficients AGIRC-ARRCO 2025

Date : 1er novembre 2025

---

## Problème identifié

Le fichier `calcul_complementaires.py` contenait des **coefficients AGIRC-ARRCO incorrects** qui ne correspondaient pas au document officiel 2025.

---

## Comparaison AVANT / APRÃˆS

### AVANT (Code incorrect)

```python
COEFFICIENTS_MINORATION_AGIRC_ARRCO = {
    (1, 4): 0.99,    # 1 Ã  4 trimestres : 99% (FAUX)
    (5, 8): 0.98,    # 5 Ã  8 trimestres : 98% (FAUX)
    (9, 12): 0.97,   # 9 Ã  12 trimestres : 97% (FAUX)
    (13, 16): 0.96,  # 13 Ã  16 trimestres : 96% (FAUX)
    (17, 20): 0.95,  # 17 Ã  20 trimestres : 95% (FAUX)
}
```

**Problème** : Les coefficients étaient par **tranche** (1-4, 5-8, etc.) au lieu d'être par **trimestre individuel**.

**Impact** : Calculs **trop favorables** pour 2, 3, 4, 6, 7, 8 trimestres manquants.

---

### APRÃˆS (Code correct)

```python
COEFFICIENTS_MINORATION_AGIRC_ARRCO = {
    0: 1.0,
    1: 0.99,
    2: 0.98,
    3: 0.97,
    4: 0.96,
    5: 0.95,
    6: 0.94,
    7: 0.93,
    8: 0.92,
    9: 0.91,
    10: 0.90,
    11: 0.89,
    12: 0.88,
    13: 0.8675,
    14: 0.855,
    15: 0.8425,
    16: 0.83,
    17: 0.8175,
    18: 0.805,
    19: 0.7925,
    20: 0.78
}
```

**Source officielle** : Document AGIRC-ARRCO "Coefficients de minoration 2025" (octobre 2024)  
Lien : https://www.agirc-arrco.fr/storage/2024/10/Coefficients-de-minorations-AA.pdf

---

## Exemples d'erreurs corrigées

### Exemple 1 : 3 trimestres manquants

**Avant (incorrect)** :
- Code utilisait la tranche (1-4) â†’ coefficient 0.99
- Pension = 91% Ã— 0.99 = **90.09%** de la pension théorique

**Après (correct)** :
- Coefficient exact pour 3 trimestres â†’ 0.97
- Pension = 91% Ã— 0.97 = **88.27%** de la pension théorique

**Écart** : Surestimation de **1.82%** de la pension complémentaire

---

### Exemple 2 : 7 trimestres manquants

**Avant (incorrect)** :
- Code utilisait la tranche (5-8) â†’ coefficient 0.98
- Pension = 87% Ã— 0.98 = **85.26%** de la pension théorique

**Après (correct)** :
- Coefficient exact pour 7 trimestres â†’ 0.93
- Pension = 87% Ã— 0.93 = **80.91%** de la pension théorique

**Écart** : Surestimation de **4.35%** de la pension complémentaire

---

### Exemple 3 : 12 trimestres manquants

**Avant (incorrect)** :
- Code utilisait la tranche (9-12) â†’ coefficient 0.97
- Pension = 84% Ã— 0.97 = **81.48%** de la pension théorique

**Après (correct)** :
- Coefficient exact pour 12 trimestres â†’ 0.88
- Pension = 84% Ã— 0.88 = **73.92%** de la pension théorique

**Écart** : Surestimation de **7.56%** de la pension complémentaire

---

## Impact financier estimé

Pour un cadre avec **6500 points AGIRC-ARRCO** et **7 trimestres manquants** :

**Valeur du point 2025** : 1,4386 â‚¬

### Calcul AVANT (incorrect)
```
Pension base = 6500 Ã— 1,4386 = 9 351 â‚¬/an
Coefficient = 0.98 (tranche 5-8)
Pension finale = 9 351 Ã— 0.98 = 9 164 â‚¬/an (764 â‚¬/mois)
```

### Calcul APRÃˆS (correct)
```
Pension base = 6500 Ã— 1,4386 = 9 351 â‚¬/an
Coefficient = 0.93 (7 trimestres)
Pension finale = 9 351 Ã— 0.93 = 8 696 â‚¬/an (725 â‚¬/mois)
```

**Différence** : **468 â‚¬/an** soit **39 â‚¬/mois** de surestimation

---

## Autres corrections apportées

### Coefficients IRCANTEC

Les mêmes corrections ont été appliquées au régime IRCANTEC (contractuels fonction publique), qui utilise les mêmes coefficients qu'AGIRC-ARRCO.

### Commentaires et documentation

- Ajout de la source officielle dans le code
- Mise Ã  jour des exemples dans les docstrings
- Correction des exemples de la fonction `obtenir_coefficient_minoration_agirc_arrco()`

---

## Règles importantes rappelées

### Quand s'applique la minoration AGIRC-ARRCO ?

La minoration **s'applique** dans ces cas :
1. Départ Ã  l'âge légal ou après, avec trimestres manquants (max 20)
2. Départ anticipé avant l'âge légal (Ã  partir de 57 ans)

La minoration **ne s'applique PAS** dans ces cas :
1. Départ au taux plein (durée requise atteinte)
2. Départ en carrière longue avant l'âge légal AVEC taux plein
3. Départ Ã  67 ans (taux plein automatique)
4. Situations d'inaptitude, invalidité, handicap
5. Retraités modestes (exonérés de CSG)

---

## Suppression du malus temporaire de 10%

**IMPORTANT** : Le coefficient de solidarité temporaire (malus de 10% pendant 3 ans) a été **supprimé** :

- **Date effective** : 1er décembre 2023 (nouveaux retraités)
- **Application rétroactive** : 1er avril 2024 (anciens retraités)

Cette suppression fait suite Ã  la réforme des retraites de 2023 qui a reporté l'âge légal de 62 Ã  64 ans.

**Ce qui reste** : Les coefficients de minoration **définitifs** (Ã  vie) corrigés dans ce fichier.

---

## Validation des corrections

### Tests effectués

1. **Syntaxe Python** : âœ… Validée avec `python3 -m py_compile`
2. **Exemples de calcul** : âœ… Vérifiés avec document officiel
3. **Cohérence IRCANTEC** : âœ… Aligné sur AGIRC-ARRCO

### Source de vérification

Document officiel : "Coefficients de minoration applicables Ã  la retraite complémentaire Agirc-Arrco 2025"  
Publié : Octobre 2024  
URL : https://www.agirc-arrco.fr/storage/2024/10/Coefficients-de-minorations-AA.pdf

---

## Actions recommandées

1. **Télécharger** le fichier `calcul_complementaires.py` corrigé
2. **Remplacer** l'ancien fichier dans le projet
3. **Tester** les calculs avec des cas réels
4. **Vérifier** les estimations déjÃ  réalisées avec l'ancien code (possible surestimation)
5. **Documenter** les corrections apportées dans votre système

---

## Fichiers livrés

âœ… `calcul_complementaires.py` - Version corrigée avec coefficients officiels 2025  
âœ… `VALEURS_REGLEMENTAIRES_2025.md` - Référentiel complet des valeurs 2025  
âœ… `CORRECTIONS_AGIRC_ARRCO.md` - Ce document explicatif

---

**Note finale** : Cette correction est **critique** pour la fiabilité des estimations de retraite complémentaire. Les calculs effectués avec l'ancien code pouvaient surestimer les pensions AGIRC-ARRCO jusqu'Ã  7,5% dans certains cas.
