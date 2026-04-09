# SKILL : Retraite Progressive (RP)
## Dispositif d'Aménagement de Fin de Carrière

**Version** : 1.0  
**Date** : 10/11/2025  
**Projet** : Calculateur Retraite - EOR

---

## 🎯 OBJECTIF DU SKILL

Ce skill guide l'analyse de l'éligibilité au dispositif de Retraite Progressive (RP), permettant de cumuler une activité salariée à temps partiel avec une fraction de pension de retraite, tout en continuant à améliorer ses droits.

**Scope** :
- ✅ Vérification des 3 conditions d'éligibilité (âge, durée, activité temps partiel)
- ✅ Calcul de la fraction de pension (40-80% temps partiel)
- ✅ Gestion multi-employeurs (depuis 2018)
- ✅ Modification, suspension, suppression
- ✅ Liquidation définitive
- ❌ Retraite progressive des non-salariés (hors scope Phase 1)
- ❌ Fonctionnaires titulaires (régime spécial)

**Base réglementaire** :  
Articles L.351-15 et L.351-16 CSS | Décret n°2017-1645 du 30/11/2017 | Circulaire CNAV 2018-31 du 21/12/2018

---

## 📋 QUAND UTILISER CE SKILL

### Déclencheurs automatiques

Utiliser ce skill **systématiquement** lorsque :
- Le client envisage de passer à temps partiel avant l'âge légal
- Le client est déjà à temps partiel et approche les 60 ans
- Le client demande à "réduire son activité progressivement"
- Le client veut "commencer sa retraite tout en travaillant"

### Mots-clés déclencheurs

- "retraite progressive"
- "RP"
- "temps partiel + retraite"
- "mi-temps fin de carrière"
- "réduire mon activité"
- "travailler à 80% et toucher une pension"

### Questions typiques du client

- "Je peux toucher une partie de ma retraite en travaillant ?"
- "Comment faire pour passer à 80% à 60 ans ?"
- "C'est quoi la retraite progressive ?"
- "Je continue à cotiser si je suis en RP ?"

---

## 📚 CONTEXTE RÉGLEMENTAIRE

### Principe général

La **retraite progressive (RP)** permet à un salarié dès 60 ans de :
- Travailler à temps partiel (40-80% d'un temps complet)
- Percevoir une fraction de sa pension de retraite (20-60% selon quotité travaillée)
- Continuer à améliorer ses droits à retraite définitive

**Avantages** :
- ✅ Transition douce vers la retraite
- ✅ Revenus combinés (salaire + fraction pension)
- ✅ Acquisition de nouveaux trimestres et points
- ✅ Amélioration de la pension définitive

**Important** : La RP est **provisoire**. À la cessation d'activité, une retraite définitive est recalculée en intégrant tous les droits acquis pendant la période de RP.

---

## ✅ LES 3 CONDITIONS CUMULATIVES D'ÉLIGIBILITÉ

Pour bénéficier de la Retraite Progressive, l'assuré doit **OBLIGATOIREMENT** remplir **3 conditions** :

### CONDITION 1 : Âge minimum (60 ans minimum)

**Règle** : Avoir atteint l'âge légal de la retraite **MOINS 2 ANS**, sans pouvoir être inférieur à **60 ans**.

**Formule** :
```
Âge minimum RP = max(Âge légal - 2 ans, 60 ans)
```

**Exemples par génération** :

| Génération | Âge légal | Âge RP |
|---|---|---|
| 1962 | 62 ans 6 mois | **60 ans 6 mois** |
| 1963 | 62 ans 9 mois | **60 ans 9 mois** |
| 1964-1967 | 63 ans | **61 ans** |
| 1968 | 63 ans 3 mois | **61 ans 3 mois** |
| 1972 | 64 ans | **62 ans** |

**Code de contrôle** : `RP_C01`

---

### CONDITION 2 : Durée d'assurance (150 trimestres minimum)

**Règle** : Justifier d'au moins **150 trimestres d'assurance** (37,5 ans) **tous régimes confondus** (y compris périodes reconnues équivalentes - PRE).

**Régimes pris en compte** :
- CNAV (régime général)
- MSA salariés et non-salariés
- RSI / SSI (artisans, commerçants)
- CIPAV et autres professions libérales
- **Régimes spéciaux** (depuis 2015)

**Périodes comptabilisées** :
- ✅ Trimestres cotisés
- ✅ Trimestres assimilés (maladie, chômage, maternité, etc.)
- ✅ Périodes reconnues équivalentes (PRE)
- ✅ Périodes étrangères (UE, conventions bilatérales)

**Code de contrôle** : `RP_C02`

---

### CONDITION 3 : Activité à temps partiel (40-80%)

**Règle** : Exercer **UNE ou PLUSIEURS** activités salariées à temps partiel, dont la durée cumulée représente **entre 40% et 80%** d'un temps complet.

#### 3a. Définition du temps partiel (Art. L.3123-1 Code du travail)

**Temps partiel = Durée du travail inférieure à** :
- Durée légale (35h/semaine ou 1607h/an)
- OU durée conventionnelle applicable dans l'entreprise/branche

**Exclus du dispositif RP** (durée non exprimée en heures) :
- ❌ VRP (sauf horaire précis)
- ❌ Mandataires sociaux / dirigeants
- ❌ Forfait jours sur l'année
- ❌ Artisans-taxis

#### 3b. Quotité de travail acceptable (40-80%)

**Formule de calcul** :
```
Quotité travaillée = (Heures temps partiel / Heures temps complet) × 100
```

**Limites** :
- **Minimum** : 40% d'un temps complet
- **Maximum** : 80% d'un temps complet

**Exemples** :

| Temps complet | Temps partiel | Quotité | Éligible RP ? |
|---|---|---|---|
| 35h/semaine | 14h/semaine | 40% | ✅ OUI |
| 35h/semaine | 24h/semaine | 69% | ✅ OUI |
| 35h/semaine | 28h/semaine | 80% | ✅ OUI |
| 35h/semaine | 12h/semaine | 34% | ❌ NON (<40%) |
| 35h/semaine | 30h/semaine | 86% | ❌ NON (>80%) |

#### 3c. Multi-employeurs (depuis 2018)

**Nouveauté 2018** : Possibilité de cumuler **plusieurs activités à temps partiel** chez différents employeurs.

**Calcul de la quotité multi-employeurs** :
```
Quotité totale = Σ (Heures employeur i / Heures temps complet i) × 100
```

**Cas particuliers** :

**Particuliers employeurs** :
- Durée conventionnelle = **40h/semaine**
- Exemple : 18h employeur 1 + 12h employeur 2 = (18/40 + 12/40) × 100 = **75%** ✅

**Assistantes maternelles** :
- Durée conventionnelle = **45h/semaine**
- Calcul par nombre moyen d'heures d'accueil par contrat

**Code de contrôle** : `RP_C03`

---

## 📐 CALCUL DE LA FRACTION DE PENSION

### Étape 1 : Calcul du montant entier provisoire

**Règle** : La pension RP est calculée comme une pension normale, mais de manière **provisoire**.

**Éléments de calcul** :
```
Pension entière provisoire = SAM × Taux × (Trimestres RG / Trimestres requis)
```

**Spécificités RP** :
- Date d'arrêt du compte : **Dernier jour du trimestre civil précédant la date d'effet**
- Taux : Peut être minoré (décote), mais **maximum 25%** (au lieu de 37,5% en droit commun)
- Décote limitée : Coefficient de minoration plafonné à **25%** (loi 2014)

**Avantages applicables** :
- ✅ Majoration pour enfants (10%)
- ✅ Surcote (si applicable)
- ✅ Majoration handicapé lourd
- ✅ Minimum contributif (si conditions remplies)

**Avantages NON applicables** :
- ❌ ASPA (allocation solidarité aux personnes âgées)

**Codes de contrôle** : `RP_C04` (SAM), `RP_C05` (Taux), `RP_C06` (Durée)

---

### Étape 2 : Calcul du pourcentage de fractionnement

**Formule simple** :
```
Fraction de pension = 100% - Quotité travaillée
```

**Exemples** :

| Quotité travaillée | Fraction pension versée |
|---|---|
| 40% | **60%** |
| 50% | **50%** |
| 60% | **40%** |
| 70% | **30%** |
| 80% | **20%** |

**Code de contrôle** : `RP_C07`

---

### Étape 3 : Montant de la fraction versée

```
Montant RP mensuel = Pension entière provisoire × Fraction de pension
```

**Exemple complet** :

```
Situation :
- SAM : 30 000 €
- Taux : 41,25% (décote car 152 trimestres au lieu de 169)
- Durée RG : 152 trimestres
- Durée requise : 166 trimestres
- Quotité travaillée : 60%

Calcul :
Pension entière provisoire = 30 000 × 41,25% × (152/166) = 11 331 € brut annuel
= 944 € brut mensuel

Fraction de pension = 100% - 60% = 40%

Montant RP mensuel = 944 € × 40% = 378 € brut/mois
```

---

## 🔄 SERVICE ET MODIFICATION DE LA RP

### Durée de service initiale : 1 an

**Règle** : La fraction de pension est servie pendant **1 an** sans modification, même si la quotité de travail change (dans la fourchette 40-80%).

**Période de référence annuelle** : Du 1er jour de la RP jusqu'au même jour l'année suivante.

**Exemple** :
```
Date d'effet RP : 1er septembre 2024
Période annuelle 1 : 1er septembre 2024 → 31 août 2025
→ Fraction figée pendant cette période

Modifications possibles à compter du : 1er septembre 2025
```

---

### Modification de la quotité de travail

**Règle** : Si la quotité de travail change au cours d'une période annuelle, la nouvelle fraction ne s'applique qu'à la **période annuelle suivante**.

**Procédure** :
1. Changement de quotité (nouveau contrat, heures modifiées)
2. Déclaration à la caisse de retraite
3. Nouveau calcul de fraction
4. Application au **1er jour de la période annuelle suivante**

**Exception** : Si le changement de quotité intervient **le jour anniversaire**, la modification prend effet immédiatement.

**Exemple** :

```
Date d'effet RP : 1er septembre 2024 (quotité 60%)
Changement : 15 mars 2025 → passage à 70%
Application nouvelle fraction (30%) : 1er septembre 2025

MAIS si changement le 1er septembre 2025 :
Application immédiate de la nouvelle fraction
```

**Code de contrôle** : `RP_C08`

---

### Suspension du paiement

**La RP est suspendue dans les cas suivants** :

#### Cas 1 : Cessation totale d'activité AVANT âge légal
- Arrêt de tous les contrats de travail à temps partiel
- Pas de demande de retraite définitive
- **Suspension** au 1er jour du mois suivant la cessation
- **Possible reprise** si nouvelle activité temps partiel ouvrant droit

#### Cas 2 : Cessation totale d'activité À/APRÈS âge légal
- Arrêt de tous les contrats à temps partiel
- Pas de demande de retraite définitive
- **Suspension** au 1er jour du mois suivant
- **Conseil** : demander la retraite définitive

#### Cas 3 : Non-réponse au questionnaire de contrôle
- Envoyé tous les ans (10 mois après date d'effet, puis annuellement)
- Non-réponse → suspension au 1er du mois suivant l'échéance de réponse
- **Rétablissement possible** si justificatifs fournis a posteriori

**Code de contrôle** : `RP_C09`

---

### Suppression de la RP (définitive)

**La RP est SUPPRIMÉE dans les cas suivants** :

#### Cas 1 : Reprise d'une activité à temps complet
- Contrat de travail à temps plein
- **Suppression** au 1er jour du mois suivant
- **Conséquence** : Fin de tout droit ultérieur à une RP

#### Cas 2 : Quotité hors limites (<40% ou >80%)
- Modification de la durée de travail sortant de la fourchette 40-80%
- **Suppression** au 1er jour du mois suivant
- **Conséquence** : Fin de tout droit ultérieur à une RP

#### Cas 3 : Demande de retraite définitive
- Cessation totale d'activité + demande de retraite
- **Suppression** au 1er jour du mois suivant la cessation
- **Conséquence** : Liquidation définitive

**Code de contrôle** : `RP_C10`

---

## 🎯 LIQUIDATION DÉFINITIVE

### Principe général

À la cessation totale d'activité, l'assuré demande sa **retraite définitive**. Celle-ci est recalculée en intégrant **tous les droits acquis** pendant la période de RP.

---

### Recalcul des éléments

**Nouveaux éléments pris en compte** :
- ✅ Salaires perçus à temps partiel pendant la RP
- ✅ Trimestres validés pendant la RP
- ✅ Nouveau SAM (25 meilleures années réactualisées)
- ✅ Nouveau taux (si taux plein atteint entre-temps)
- ✅ Surcote (si dépassement durée requise après âge légal)

**Date d'arrêt du compte définitive** : Dernier jour du trimestre civil précédant la liquidation définitive.

---

### Règle du montant minimal

**Garantie** : Le montant de la pension définitive **ne peut pas être inférieur** au montant entier provisoire ayant servi de base au calcul de la RP, **revalorisé**.

**Formule** :
```
Pension définitive = max(Pension recalculée, Pension entière provisoire revalorisée)
```

**Exemple** :

```
Situation :
Pension entière provisoire (2018) : 11 331 € brut/an
Revalorisation 2018-2024 : +10%
Pension entière provisoire revalorisée : 11 331 × 1,10 = 12 464 €

Pension recalculée 2024 : 14 239 € brut/an

Pension définitive servie : 14 239 € (la plus élevée)
```

**Code de contrôle** : `RP_C11`

---

### Date d'effet de la retraite définitive

**Règles de droit commun** (Art. R.351-37 CSS) :
- 1er jour du mois suivant la demande
- Ou date choisie par l'assuré (si postérieure)

**Possibilité de départ anticipé avant âge légal** :
- Si conditions de carrière longue remplies entre-temps
- Si situation de handicap reconnue
- Retraite anticipée se substitue alors à la RP

**Code de contrôle** : `RP_C12`

---

### Cumul emploi-retraite après liquidation définitive

**Règle** : Après liquidation définitive, les règles de cumul emploi-retraite s'appliquent normalement.

**Cumul total possible si** :
- Âge légal atteint + taux plein (ou 67 ans)
- Liquidation de toutes les pensions personnelles

**Sinon** : Cumul plafonné.

**Important** : Pendant la RP, le salarié **n'est pas en cumul emploi-retraite** → les cotisations génèrent de nouveaux droits.

---

## 🔍 CONTRÔLES DE COHÉRENCE ET ALERTES

### Contrôles techniques (Rouge = BLOQUANT)

| ID | Contrôle | Type | Message |
|---|---|---|---|
| **RP_C01** | Âge >= Âge RP minimal | ERREUR | "Âge insuffisant. Âge minimum : {age_rp} ans." |
| **RP_C02** | Trimestres >= 150 | ERREUR | "Durée d'assurance insuffisante. Il manque {x} trimestres." |
| **RP_C03** | 40% <= Quotité <= 80% | ERREUR | "Quotité de travail hors limites ({quotite}%). Doit être entre 40 et 80%." |
| **RP_C04** | SAM > 0 | ERREUR | "SAM nul ou négatif. Vérifier carrière." |
| **RP_C05** | 37,5% <= Taux <= 50% | ERREUR | "Taux hors limites ({taux}%). Vérifier calcul décote." |
| **RP_C06** | Trimestres RG > 0 | ERREUR | "Aucun trimestre au régime général." |
| **RP_C07** | 20% <= Fraction <= 60% | ERREUR | "Fraction pension hors limites ({fraction}%)." |
| **RP_C08** | Modification <= 1 an | WARNING | "Modification trop fréquente. Attendre période annuelle." |
| **RP_C09** | Justificatifs fournis | WARNING | "Questionnaire non renvoyé. Risque de suspension." |
| **RP_C10** | Activité conforme | WARNING | "Activité hors limites. Risque de suppression." |
| **RP_C11** | Pension définitive >= Provisoire revalorisée | CHECK | "Règle du minimum respectée." |
| **RP_C12** | Conditions cumul vérifiées | INFO | "Cumul emploi-retraite après liquidation définitive possible." |

---

### Alertes métier (Orange/Jaune = ATTENTION)

| Catégorie | Type | Message | Action recommandée |
|---|---|---|---|
| **Âge proche** | ORANGE | "Vous aurez {age_legal} ans dans {mois} mois. Comparer RP vs retraite complète." | Simulation comparative |
| **Taux plein proche** | ORANGE | "Il manque {x} trimestres pour le taux plein. Attendre {date} ?" | Arbitrage décote/attente |
| **Quotité limite** | JAUNE | "Quotité travaillée proche de la limite {limite}%. Attention aux modifications." | Vigilance contrat |
| **Multi-employeurs** | JAUNE | "Plusieurs employeurs détectés. Vérifier totalisation quotités." | Contrôle cumul heures |
| **Pension provisoire faible** | ORANGE | "Pension provisoire faible ({montant}€/mois). Envisager report ?" | Simulation avec report |

---

## 💡 CAS PARTICULIERS

### Cas 1 : Pension d'invalidité

**Situation** : Titulaire d'une pension d'invalidité

**Traitement** :
- Attribution de la RP entraîne **suspension** de la pension d'invalidité
- L'assuré conserve la qualité **d'ex-invalide**
- À la liquidation définitive : taux plein dès l'âge légal + possibilité MTP + ASPA

**Alerte** : `RP_INVALIDE`

---

### Cas 2 : Proche de l'éligibilité (manque quelques trimestres)

**Situation** : 147 trimestres au lieu de 150 requis

**Options** :
1. Continuer à cotiser 9 mois (3 trimestres)
2. Racheter des trimestres VPLR (si rachat avant 2011 pour RACL)
3. Vérifier anomalies du relevé de carrière

**Conseil** : Souvent plus rentable de continuer à travailler que de racheter.

---

### Cas 3 : Multi-employeurs complexes

**Situation** : Salarié de 3 employeurs différents (entreprise + 2 particuliers)

**Calcul** :
```
Employeur 1 (entreprise) : 18h/35h = 51,4%
Employeur 2 (particulier) : 10h/40h = 25%
Employeur 3 (particulier) : 5h/40h = 12,5%
Total : 51,4% + 25% + 12,5% = 88,9% → 89% (arrondi)

❌ Hors limites (>80%) → Non éligible
```

**Solution** : Réduire les heures chez un des employeurs pour passer sous 80%.

---

### Cas 4 : Assistante maternelle

**Situation** : Garde 3 enfants (3 contrats)

**Calcul spécifique** :
```
Enfant 1 : 33h/semaine
Enfant 2 : 30h/semaine
Enfant 3 : 33h/semaine
Total : 96h/semaine

Nombre moyen d'heures par contrat = 96 / 3 = 32h/contrat
Quotité = (32 / 45) × 100 = 71%

Fraction pension = 100% - 71% = 29%
```

**Particularité** : Calcul sur **47 semaines** (et non 52) pour la mensualisation/annualisation.

---

### Cas 5 : Passage RP → Carrière longue

**Situation** : En RP à 61 ans, remplit conditions RACL à 62 ans

**Traitement** :
- Possibilité de liquider la retraite anticipée carrière longue
- Remplace le service de la RP
- Retraite définitive au taux plein dès 62 ans

**Conseil** : Vérifier l'intérêt financier (RP + salaire vs retraite complète carrière longue).

---

## 📊 COMPARAISON RP vs AUTRES OPTIONS

### RP vs Retraite complète à l'âge légal

| Critère | Retraite Progressive | Retraite complète âge légal |
|---|---|---|
| **Âge de début** | 60 ans (ou âge légal -2) | Âge légal (62-64 ans) |
| **Revenus** | Salaire TP + fraction pension | Pension complète uniquement |
| **Acquisition droits** | ✅ Nouveaux trimestres/points | ❌ Aucun nouveau droit |
| **Pension définitive** | ✅ Améliorée | Figée |
| **Activité** | Obligatoire (40-80%) | Cessation totale (ou cumul) |

**Recommandation** :
- RP avantageuse si **taux plein non atteint** (permet d'améliorer le taux)
- RP avantageuse si **souhait de transition progressive**
- Retraite complète si **taux plein atteint** et besoin de revenus complets

---

### RP vs Cumul emploi-retraite

| Critère | Retraite Progressive | Cumul emploi-retraite |
|---|---|---|
| **Âge de début** | 60 ans (ou âge légal -2) | Âge légal minimum |
| **Acquisition droits** | ✅ OUI | ❌ NON (sauf cas particuliers) |
| **Pension** | Fraction provisoire | Pension définitive complète |
| **Revenus** | Salaire TP + fraction | Salaire (libre) + pension |
| **Plafond revenus** | Aucun | Oui (si cumul plafonné) |

**Recommandation** :
- **RP préférable** si **pas encore au taux plein** (améliore la pension définitive)
- **Cumul emploi-retraite** si **taux plein atteint** et souhait de revenus élevés

---

## 🗂️ DOCUMENTS NÉCESSAIRES

### Demande initiale de RP

**Documents obligatoires** :
- ✅ Formulaire de demande de retraite progressive
- ✅ Contrat(s) de travail à temps partiel en cours
- ✅ Déclaration sur l'honneur (pas d'autre activité professionnelle)
- ✅ Attestation(s) employeur(s) précisant la durée temps complet applicable
- ✅ Bulletins de salaire des 12 derniers mois civils

**Délai** : Dépôt au moins 4 mois avant la date d'effet souhaitée.

---

### Modification annuelle (contrôle)

**Documents** :
- ✅ Nouveau contrat de travail (si modification)
- ✅ Attestation employeur mise à jour
- ✅ Bulletins de salaire de la période écoulée

**Envoi** : Questionnaire de contrôle envoyé 10 mois après la date d'effet, puis annuellement.

---

### Liquidation définitive

**Documents** :
- ✅ Demande de retraite définitive (formulaire standard)
- ✅ Justificatif de cessation d'activité (certificat de travail, attestation employeur)
- ✅ Relevé de carrière à jour
- ✅ Bulletins de salaire de la période RP

---

## 📞 INFORMATIONS À COMMUNIQUER AU CLIENT

### Si ÉLIGIBLE à la RP

```markdown
✅ VOUS ÊTES ÉLIGIBLE À LA RETRAITE PROGRESSIVE

Vous remplissez les 3 conditions :
- ✅ Âge : {age} ans (minimum {age_rp} ans)
- ✅ Durée d'assurance : {trimestres} trimestres (minimum 150)
- ✅ Activité temps partiel : {quotite}% (entre 40 et 80%)

💰 ESTIMATION RETRAITE PROGRESSIVE :

Situation actuelle :
- Salaire annuel temps partiel : {salaire_tp} € brut
- Pension entière provisoire : {pension_entiere} € brut/mois

Avec RP (quotité {quotite}%) :
- Fraction de pension versée : {fraction}%
- Montant mensuel RP : {montant_rp} € brut/mois
- REVENUS TOTAUX : {salaire_tp_mensuel + montant_rp} € brut/mois

✨ AVANTAGES :
- Vous continuez à acquérir des trimestres et des points
- Votre pension définitive sera améliorée
- Transition progressive vers la retraite

⚠️ POINTS D'ATTENTION :
- La fraction de pension est figée pendant 1 an
- Si vous changez d'employeur, vérifier la quotité totale
- Questionnaire de contrôle annuel obligatoire

📄 DOCUMENTS À FOURNIR :
- Formulaire de demande RP
- Contrat de travail temps partiel
- Attestation employeur
- 12 derniers bulletins de salaire

📅 DÉLAI : Déposer la demande 4 mois avant la date souhaitée.
```

---

### Si NON ÉLIGIBLE

```markdown
❌ VOUS N'ÊTES PAS ÉLIGIBLE À LA RETRAITE PROGRESSIVE

Raison(s) :
- [Condition(s) non remplie(s)]

[Si âge insuffisant :]
⏳ Vous pourrez bénéficier de la RP à partir de : {date_eligibilite}

[Si trimestres manquants :]
📊 Il manque {x} trimestres pour atteindre les 150 requis.
Options :
- Continuer à cotiser {mois} mois
- Racheter des trimestres (coût estimé : {cout} €)
- Vérifier anomalies du relevé de carrière

[Si quotité hors limites :]
⚖️ Votre quotité de travail ({quotite}%) est hors limites (40-80%).
Solutions :
- Si < 40% : augmenter votre temps de travail
- Si > 80% : réduire votre temps de travail

📞 N'hésitez pas à me contacter pour étudier vos options.
```

---

## ⚠️ ERREURS À ÉVITER

### ❌ ERREUR 1 : Confondre RP et cumul emploi-retraite

**Mauvaise explication** :
"Vous touchez votre retraite complète + vous travaillez."

**Bonne explication** :
"La RP est une retraite **provisoire et partielle** (20-60%), calculée proportionnellement à votre temps de travail. Ce n'est PAS un cumul emploi-retraite. Vous continuez à acquérir des droits."

**Pourquoi c'est une erreur** :
- RP = pension provisoire partielle + acquisition de droits
- Cumul = pension définitive complète + pas d'acquisition de droits

---

### ❌ ERREUR 2 : Oublier la limite de décote à 25%

**Mauvais calcul** :
```
Génération 1965, 152 trimestres validés, 169 requis
Décote : 17 trimestres × 0,625% = 10,625%
Taux : 50% - 10,625% = 39,375%
```

**Bon calcul** :
```
Décote limitée à 25% pour la RP
Décote réelle : min(17 × 0,625%, 25%) = 10,625%
Taux : 50% - 10,625% = 39,375% ✅
```

**Pourquoi c'est important** :
La loi de 2014 a plafonné la décote RP à 25% (au lieu de 37,5% en droit commun) pour encourager le dispositif.

---

### ❌ ERREUR 3 : Ne pas vérifier la quotité multi-employeurs

**Mauvais raisonnement** :
"Client travaille 25h chez employeur 1 (35h temps complet) = 71% → éligible."

**Bon raisonnement** :
"Client travaille aussi 10h chez particulier employeur (40h temps complet) = 25%.
Total : 71% + 25% = 96% → NON ÉLIGIBLE (>80%)."

**Pourquoi c'est une erreur** :
Depuis 2018, il faut **additionner toutes les quotités** de tous les employeurs.

---

### ❌ ERREUR 4 : Promettre une date d'effet trop rapide

**Mauvaise communication** :
"Vous pouvez commencer votre RP le mois prochain."

**Bonne communication** :
"Il faut déposer votre demande **au moins 4 mois avant** la date souhaitée. Pour un départ au 1er juillet, dépôt avant le 1er mars."

**Pourquoi c'est important** :
Délais d'instruction + coordination entre régimes (si polypensionné).

---

### ❌ ERREUR 5 : Oublier la règle du montant minimal

**Mauvais conseil** :
"Votre pension définitive sera de {pension_recalculee} €."

**Bon conseil** :
"Votre pension définitive sera au minimum de {pension_entiere_revalorisee} € (pension provisoire revalorisée). Si le recalcul donne plus ({pension_recalculee} €), c'est ce montant qui sera servi."

**Pourquoi c'est important** :
Protection du bénéficiaire : la RP ne peut jamais aboutir à une pension définitive inférieure à la pension provisoire initiale.

---

## 🎓 EXEMPLES PRATIQUES

### Exemple 1 : Cas simple mono-employeur

**Situation** :
- Né le 15/03/1965 (60 ans en 2025)
- 165 trimestres tous régimes
- Temps complet actuel : 35h/semaine
- Souhaite passer à 24h/semaine (69%)

**Analyse** :
```
✅ Condition 1 (âge) : 60 ans >= 60 ans (âge légal 63 ans - 2 = 61 ans, mais minimum 60)
✅ Condition 2 (durée) : 165 trimestres >= 150 trimestres
✅ Condition 3 (quotité) : 69% entre 40% et 80%

→ ÉLIGIBLE
```

**Calcul** :
```
SAM : 28 000 €
Taux : 46,25% (décote 10 trimestres : 169 - 165 = 4 manquants → arbitrage 4 × 0,625% = 2,5%, mais âge 60 ans → 12 trim jusqu'à 63 ans, on prend le min(4, 12) = 4 → décote 2,5%)
Durée RG : 158 trimestres
Durée requise : 169 trimestres

Pension entière provisoire :
28 000 × 46,25% × (158/169) = 12 119 € brut/an = 1 010 € brut/mois

Fraction : 100% - 69% = 31%

Montant RP : 1 010 € × 31% = 313 € brut/mois

Revenus totaux :
- Salaire temps partiel : 1 800 € brut/mois (estimation)
- Pension RP : 313 € brut/mois
- TOTAL : 2 113 € brut/mois
```

---

### Exemple 2 : Multi-employeurs (particuliers)

**Situation** :
- Née le 20/06/1964 (61 ans en 2025)
- 172 trimestres tous régimes
- Employeur 1 (particulier) : 18h/semaine
- Employeur 2 (particulier) : 12h/semaine
- Durée conventionnelle : 40h/semaine

**Analyse** :
```
✅ Condition 1 (âge) : 61 ans >= 61 ans (âge légal 63 ans - 2)
✅ Condition 2 (durée) : 172 trimestres >= 150 trimestres

Quotité :
Employeur 1 : 18/40 = 45%
Employeur 2 : 12/40 = 30%
Total : 45% + 30% = 75%

✅ Condition 3 (quotité) : 75% entre 40% et 80%

→ ÉLIGIBLE
```

**Calcul** :
```
SAM : 22 500 €
Taux : 50% (taux plein atteint)
Durée RG : 164 trimestres
Durée requise : 169 trimestres

Pension entière provisoire :
22 500 × 50% × (164/169) = 10 917 € brut/an = 910 € brut/mois

Fraction : 100% - 75% = 25%

Montant RP : 910 € × 25% = 228 € brut/mois
```

---

### Exemple 3 : Assistante maternelle

**Situation** :
- Née le 10/09/1966 (58 ans en 2024)
- 158 trimestres tous régimes
- Garde 3 enfants (3 contrats) :
  - Enfant 1 : 33h/semaine
  - Enfant 2 : 30h/semaine
  - Enfant 3 : 33h/semaine
- Durée conventionnelle : 45h/semaine

**Analyse** :
```
❌ Condition 1 (âge) : 58 ans < 61 ans (âge légal 63 ans - 2)
   → Non éligible pour l'instant
   → Éligibilité à partir du 10/09/2027 (61 ans)
```

**Simulation si éligible en 2027** :
```
✅ Condition 2 (durée) : 158 trimestres >= 150 (en 2027 : 158 + 12 = 170)

Quotité :
Total heures : 33 + 30 + 33 = 96h/semaine
Nombre moyen par contrat : 96/3 = 32h/contrat
Quotité : 32/45 = 71%

✅ Condition 3 (quotité) : 71% entre 40% et 80%

→ ÉLIGIBLE à partir de septembre 2027
```

---

## 📚 SOURCES RÉGLEMENTAIRES

### Textes de loi
- **Code de la Sécurité Sociale** : Articles L.351-15 et L.351-16
- **Loi n°2014-40 du 20/01/2014** : Réforme des retraites (amélioration RP)
- **Loi n°2016-1827 du 23/12/2016** : Extension multi-employeurs

### Décrets d'application
- **Décret n°2014-1513 du 16/12/2014** : Barème fraction simplifié
- **Décret n°2017-1645 du 30/11/2017** : Multi-employeurs et modalités de calcul

### Circulaires CNAV
- **Circulaire 2014-65 du 23/12/2014** : Application de la loi 2014
- **Circulaire 2017-43 du 27/12/2017** : Dispositions avant extension multi-employeurs
- **Circulaire 2018-31 du 21/12/2018** : **Circulaire de référence** (version consolidée)

---

## 🚀 ÉVOLUTIONS FUTURES

### Phase 2 (Court terme)
- Ajout calcul précis des régimes complémentaires (AGIRC-ARRCO, IRCANTEC)
- Simulation impact cotisations sur temps plein (option L.241-3-1)
- Intégration LURA (liquidation unique régimes alignés)

### Phase 3 (Moyen terme)
- Agents non-titulaires de la fonction publique
- Assistantes maternelles (règles détaillées mensualisation/annualisation)
- Retraite progressive des non-salariés (artisans, commerçants, professions libérales)

---

## 📋 CHECKLIST DE VALIDATION

```
✅ DONNÉES COLLECTÉES
□ Date de naissance exacte
□ Relevé de carrière à jour (tous régimes)
□ Nombre de trimestres validés
□ Contrat(s) de travail actuel(s)
□ Durée temps complet applicable(s)
□ Durée temps partiel envisagée

✅ CALCULS EFFECTUÉS
□ Âge minimum RP calculé
□ 150 trimestres vérifiés (tous régimes + PRE)
□ Quotité(s) calculée(s) (40-80%)
□ SAM estimé
□ Taux calculé (avec plafond décote 25%)
□ Pension entière provisoire calculée
□ Fraction de pension déterminée
□ Montant RP mensuel estimé

✅ ÉLIGIBILITÉ DÉTERMINÉE
□ 3 conditions vérifiées
□ Date d'effet possible identifiée
□ Comparaison RP vs retraite complète
□ Comparaison RP vs cumul emploi-retraite

✅ EXPLICATION CLIENT
□ Éligibilité clairement énoncée (OUI/NON)
□ Si OUI : montants estimés + avantages
□ Si NON : raisons détaillées + solutions
□ Documents à fournir listés
□ Délais et démarches expliqués

✅ VALIDATION TECHNIQUE
□ Cohérence SAM > 0
□ Taux entre 37,5% et 50%
□ Quotité entre 40% et 80%
□ Fraction entre 20% et 60%
□ Règle montant minimal rappelée
```

---

## 🏁 FIN DU SKILL RETRAITE PROGRESSIVE

**Rappel** : Ce skill couvre le dispositif de Retraite Progressive pour les **salariés du secteur privé** (régime général). Les fonctionnaires titulaires et les non-salariés relèvent de règles spécifiques non couvertes en Phase 1.

**Contact projet** : Jeff - EOR  
**Dernière mise à jour** : 10/11/2025
