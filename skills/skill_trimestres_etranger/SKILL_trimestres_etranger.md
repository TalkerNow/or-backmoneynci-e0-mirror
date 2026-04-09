# SKILL : TRIMESTRES ÉTRANGER

## 🎯 CONTEXTE D'UTILISATION

### Quand utiliser ce skill ?

**Situations client** :
- ✅ Client a travaillé à l'étranger (UE, convention bilatérale, ou pays tiers)
- ✅ Question sur l'impact des périodes étrangères sur la retraite française
- ✅ Besoin de vérifier si un pays permet la totalisation des trimestres
- ✅ Estimation de la pension française avec périodes étrangères
- ✅ Explication du principe des "deux pensions" (France + pays étranger)

**Déclencheur automatique** :
- Mention de périodes à l'étranger dans le relevé de carrière
- Client indique avoir travaillé hors de France
- Questions sur accords de sécurité sociale internationaux

---

## 📋 CATÉGORIES DE PAYS - CLASSIFICATION

### 🇪🇺 CATÉGORIE A : UE/EEE/Suisse (32 pays)
**Totalisation : AUTOMATIQUE**

**Pays concernés** :
- 27 pays UE : Allemagne, Autriche, Belgique, Bulgarie, Chypre, Croatie, Danemark, Espagne, Estonie, Finlande, France, Grèce, Hongrie, Irlande, Italie, Lettonie, Lituanie, Luxembourg, Malte, Pays-Bas, Pologne, Portugal, République Tchèque, Roumanie, Slovaquie, Slovénie, Suède
- 3 pays EEE : Norvège, Islande, Liechtenstein
- Suisse
- Royaume-Uni (accord post-Brexit)

**Base légale** : Règlements européens 883/2004 et 987/2009

**Avantages** :
- Totalisation automatique des trimestres
- Coordination des régimes simplifiée
- Formulaires standardisés (E205, E207)

---

### 🌍 CATÉGORIE B : Convention bilatérale (41 pays)
**Totalisation : SOUS CONDITIONS**

**Pays concernés** :
Algérie, Andorre, Argentine, Bénin, Bosnie-Herzégovine, Brésil, Cameroun, **Canada**, Cap-Vert, Chili, Congo, Corée du Sud, Côte d'Ivoire, **États-Unis**, Gabon, Guernesey, Inde, Israël, Japon, Jersey, Macédoine, Mali, Maroc, Mauritanie, Monaco, Monténégro, Niger, Nouvelle-Calédonie, Philippines, Polynésie Française, Québec, Saint-Marin, Sénégal, Serbie, Togo, Tunisie, Turquie, Uruguay

**Base légale** : Conventions bilatérales France-Pays

**Particularités** :
- Conditions spécifiques à chaque convention
- Vérification obligatoire sur www.cleiss.fr
- Formulaires de liaison spécifiques

⚠️ **ATTENTION** : Madagascar a une convention mais SANS dispositions vieillesse

---

### 🚫 CATÉGORIE C : Sans accord
**Totalisation : IMPOSSIBLE**

**Pays concernés** : Tous les autres pays (Chine, Russie, Australie, etc.)

**Impact** :
- ❌ Les trimestres ne comptent PAS pour la retraite française
- ❌ Aucune totalisation possible
- ℹ️ Droits à pension éventuels uniquement dans le pays concerné

---

## 🔄 WORKFLOW CONSULTANT - ÉTAPES

### ÉTAPE 1 : Identification du pays

**Objectif** : Déterminer la catégorie du pays et la possibilité de totalisation

**Action** :
```python
from calcul_trimestres_etranger import identifier_statut_pays

resultat = identifier_statut_pays("Canada")
```

**Contrôles activés** :
- ✅ **TRE_C01** (ROUGE) : Pays identifié dans les listes officielles
- ✅ **TRE_C03** (ROUGE) : Totalisation possible selon statut pays

**Output** :
```json
{
  "pays": "CANADA",
  "statut": "CONVENTION_BILATERALE",
  "totalisation_possible": true,
  "description": "Pays avec convention bilatérale",
  "controles": ["TRE_C01", "TRE_C03"],
  "alerte_niveau": "JAUNE"
}
```

**🔴 Si ROUGE** : Pays sans accord → Expliquer au client que trimestres NON comptabilisés
**🟡 Si JAUNE** : Convention bilatérale → Vérifier conditions spécifiques
**🟢 Si VERT** : UE/EEE/Suisse → Totalisation automatique

---

### ÉTAPE 2 : Validation des dates et cohérence

**Objectif** : Vérifier format dates et cohérence avec relevé français

**Action** :
```python
from calcul_trimestres_etranger import calculer_trimestres_periode

resultat = calculer_trimestres_periode("01/01/2000", "31/12/2009")
```

**Contrôles activés** :
- ✅ **TRE_C04** (ORANGE) : Dates valides et cohérentes
- ✅ **TRE_C02** (ORANGE) : Cohérence période avec relevé de carrière

**Output** :
```json
{
  "trimestres_totalises": 40,
  "date_debut": "01/01/2000",
  "date_fin": "31/12/2009",
  "duree_jours": 3653,
  "annees_completes": 10,
  "detail_par_annee": {...},
  "controles": ["TRE_C04"],
  "alerte_niveau": "VERT"
}
```

**⚠️ Points de vigilance** :
- Vérifier qu'il n'y a pas de chevauchement avec périodes françaises
- Si chevauchement → Appliquer règle max 4 trimestres/an

---

### ÉTAPE 3 : Calcul d'impact sur le taux plein

**Objectif** : Analyser l'impact des trimestres étrangers sur le droit à pension française

**Action** :
```python
from calcul_trimestres_etranger import analyser_impact_trimestres_etranger

resultat = analyser_impact_trimestres_etranger(
    pays="Canada",
    date_debut="01/01/2000",
    date_fin="31/12/2009",
    trimestres_francais=130,
    duree_requise_taux_plein=167
)
```

**Contrôles activés** :
- ✅ **TRE_C05** (ROUGE) : Absence de doublon de trimestres
- ✅ **TRE_C06** (ORANGE) : Respect limite 4 trimestres/an

**Output** :
```json
{
  "pays": "CANADA",
  "trimestres_francais": 130,
  "trimestres_totalises": 40,
  "trimestres_totaux_duree_assurance": 170,
  "duree_requise_taux_plein": 167,
  "taux_plein_atteint": true,
  "trimestres_manquants": 0,
  "coefficient_proratisation_pension_francaise": 0.778,
  "impact": {
    "taux_liquidation": "Taux plein (50%)",
    "montant_pension": "Calculé sur 130 trimestres français (prorata 77.8%)",
    "pension_etrangere": "Le CANADA versera sa propre pension pour les 40 trimestres cotisés"
  },
  "metadata": {
    "controles_passes": ["TRE_C01", "TRE_C02", "TRE_C03", "TRE_C04", "TRE_C05", "TRE_C06"],
    "alerte_niveau": "JAUNE",
    "token_estimate": 1500
  }
}
```

**💡 Interprétation** :
- **Taux plein atteint** : OUI (170 trimestres ≥ 167 requis)
- **Pension française** : Calculée sur 130 trimestres français uniquement (prorata 77.8%)
- **Pension canadienne** : Le Canada versera sa propre pension pour les 40 trimestres

---

### ÉTAPE 4 : Calcul du montant de la pension française

**Objectif** : Calculer la pension française avec prorata

**Action** :
```python
from calcul_trimestres_etranger import calculer_pension_avec_prorata_etranger

resultat = calculer_pension_avec_prorata_etranger(
    sam=30000,
    taux_liquidation=50.0,
    trimestres_francais=130,
    trimestres_totalises_etranger=40,
    duree_requise=167
)
```

**Output** :
```json
{
  "pension_theorique_annuelle": 15269.46,
  "pension_theorique_mensuelle": 1272.45,
  "pension_francaise_annuelle": 11764.71,
  "pension_francaise_mensuelle": 980.39,
  "coefficient_prorata": 0.765,
  "note": "Le pays étranger versera sa propre pension pour les 40 trimestres cotisés",
  "metadata": {
    "token_estimate": 2000
  }
}
```

**📊 Résultat** :
- Pension théorique (si tout en France) : 15 269 €/an
- **Pension française réelle** : **11 765 €/an** (980 €/mois)
- Pension canadienne : À calculer par le Canada

---

### ÉTAPE 5 : Documentation et recommandations

**Objectif** : Informer le client des documents nécessaires

**Contrôles activés** :
- ✅ **TRE_C07** (JAUNE) : Documentation complète pour validation CNAV

**Documents requis selon catégorie** :

**🇪🇺 UE/EEE/Suisse** :
- Formulaire E205 (certificat périodes d'assurance)
- Formulaire E207 (certificat périodes de résidence)
- Attestation employeur étranger

**🌍 Convention bilatérale** :
- Formulaire de liaison spécifique à la convention
- Attestation organisme étranger
- Bulletins de salaire
- Justificatifs cotisations

**🚫 Sans accord** :
- Aucun document requis (périodes non comptabilisées)

**Ressources** : www.cleiss.fr/formulaires

---

## ⚠️ CONTRÔLES QUALITÉ (IDs TRE_C01-C07)

### 🔴 CONTRÔLES BLOQUANTS (ROUGE)

#### TRE_C01 : Pays identifié dans les listes officielles
- **Niveau** : ROUGE - BLOQUANT
- **Vérification** : Le pays doit être dans les listes officielles
- **Erreur** : "⛔ Pays '{pays}' non identifié dans les listes officielles"
- **Action** : Vérifier orthographe, consulter www.cleiss.fr
- **Token impact** : +200 tokens

#### TRE_C03 : Totalisation possible selon statut pays
- **Niveau** : ROUGE - BLOQUANT
- **Vérification** : Le pays doit avoir un accord (UE ou Convention)
- **Erreur** : "⛔ TOTALISATION IMPOSSIBLE : Le pays '{pays}' n'a pas d'accord"
- **Action** : Expliquer au client que trimestres NON comptabilisés
- **Token impact** : +400 tokens

#### TRE_C05 : Absence de doublon de trimestres
- **Niveau** : ROUGE - BLOQUANT
- **Vérification** : Pas de double comptabilisation France/Étranger
- **Erreur** : "⛔ DOUBLON DÉTECTÉ : Risque double comptabilisation"
- **Action** : Analyser relevé, appliquer règle coordination
- **Token impact** : +350 tokens

---

### 🟠 CONTRÔLES AVERTISSEMENT (ORANGE)

#### TRE_C02 : Cohérence période avec relevé de carrière
- **Niveau** : ORANGE - AVERTISSEMENT
- **Vérification** : Pas de chevauchement avec périodes françaises
- **Erreur** : "⚠️ Possible chevauchement avec périodes françaises"
- **Action** : Comparer relevé CNAV, vérifier cumul
- **Token impact** : +300 tokens

#### TRE_C04 : Dates valides et cohérentes
- **Niveau** : ORANGE - AVERTISSEMENT
- **Vérification** : Format JJ/MM/AAAA et date_fin > date_debut
- **Erreur** : "⚠️ DATES INVALIDES : Format incorrect"
- **Action** : Corriger format, vérifier ordre chronologique
- **Token impact** : +100 tokens

#### TRE_C06 : Respect limite 4 trimestres/an
- **Niveau** : ORANGE - AVERTISSEMENT
- **Vérification** : Max 4 trimestres par année civile
- **Erreur** : "⚠️ DÉPASSEMENT : Année {annee} > 4 trimestres"
- **Action** : Recalculer en plafonnant à 4
- **Token impact** : +250 tokens

---

### 🟡 CONTRÔLES RECOMMANDATION (JAUNE)

#### TRE_C07 : Documentation complète pour validation CNAV
- **Niveau** : JAUNE - RECOMMANDATION
- **Vérification** : Documents nécessaires mentionnés
- **Erreur** : "💡 RECOMMANDATION : Prévoir documents validation"
- **Action** : Lister documents requis selon catégorie pays
- **Token impact** : +200 tokens

---

## 💬 EXPLICATION CLIENT - PHRASES TYPES

### 🎯 Principe de la totalisation

**À dire** :
> "La France et {pays} ont signé un accord permettant d'additionner vos périodes d'assurance pour déterminer si vous avez droit à une pension. Cependant, chaque pays verse SA PROPRE pension calculée uniquement sur les périodes cotisées dans ce pays."

**Ne PAS dire** :
- ❌ "Vos trimestres étrangers comptent pour votre retraite française"
- ❌ "Vous toucherez une seule pension qui additionne tout"

---

### 💶 Vous recevrez DEUX pensions

**À dire** :
> "Vous recevrez DEUX pensions distinctes :
> 1. Une pension de la France (calculée sur vos {nb} trimestres français)
> 2. Une pension de {pays} (calculée sur vos {nb} trimestres cotisés là-bas)
> 
> Chaque pension sera versée par le pays concerné, à des montants et des dates potentiellement différents."

**Exemple chiffré** :
> "Dans votre cas :
> - Pension française : environ {montant} €/mois (sur 130 trimestres français)
> - Pension canadienne : à calculer par le Canada (sur 40 trimestres canadiens)
> - Total estimé : {montant_france} + {montant_canada_estimé}"

---

### 📐 Calcul au prorata

**À dire** :
> "Pour calculer votre pension française, la CNAV va :
> 1. Totaliser vos trimestres (France + {pays}) pour vérifier le taux plein
> 2. Appliquer un prorata : seuls vos trimestres FRANÇAIS sont pris en compte pour le montant
> 
> Dans votre cas : {trimestres_francais} trimestres français / {duree_requise} requis = {coefficient}%"

**Exemple** :
> "Vous avez 130 trimestres en France et 40 au Canada = 170 trimestres totaux.
> Taux plein atteint (170 ≥ 167) → Taux 50% ✅
> Mais le montant sera calculé sur 130/167 = 77,8% de ce qu'aurait été une carrière complète en France."

---

### 🚫 Pays sans accord

**À dire** :
> "Malheureusement, la France n'a pas d'accord de sécurité sociale avec {pays}. Cela signifie que :
> ❌ Ces trimestres ne compteront PAS pour votre retraite française
> ❌ Ils ne seront pas totalisés avec vos trimestres français
> 
> ℹ️ Cependant, vous pourriez avoir droit à une pension du {pays} si vous remplissez les conditions de leur système de retraite. Je vous recommande de contacter l'organisme de retraite de ce pays directement."

**Ressources** :
- "Pour en savoir plus : www.cleiss.fr/accords"

---

### 📄 Démarches à prévoir

**À dire** :
> "Pour faire valider vos périodes à l'étranger lors de votre demande de retraite, vous devrez fournir :
> - {liste documents selon catégorie}
> 
> Ces documents permettront à la CNAV de coordonner avec l'organisme étranger et de calculer vos droits."

**Timing** :
> "Je vous recommande de rassembler ces documents dès maintenant, car les délais d'obtention peuvent être longs (3 à 6 mois parfois)."

---

## 📊 TOKEN ESTIMATES PAR CAS

### Cas simple (1000-1500 tokens)
**Profil** :
- Pays UE/EEE/Suisse
- 1 période unique
- Dates valides
- Pas de doublon
- Pas de chevauchement

**Contrôles actifs** : TRE_C01, TRE_C03, TRE_C04, TRE_C06

---

### Cas moyen (1500-2500 tokens)
**Profil** :
- Pays convention bilatérale
- Vérification cohérence relevé
- Documentation à mentionner
- Explications principe prorata

**Contrôles actifs** : TRE_C01 à TRE_C07 (tous)

---

### Cas complexe (2500-3500 tokens)
**Profil** :
- Pays sans accord OU
- Multiples périodes étrangères OU
- Doublons détectés OU
- Chevauchements périodes

**Contrôles actifs** : TRE_C01 à TRE_C07 (tous)
**Explications supplémentaires** :
- Principe de coordination des régimes
- Règle max 4 trimestres/an
- Recommandations démarches

---

## 🔗 UTILISATION API POUR N8N

### Format standardisé

```python
from calcul_trimestres_etranger import api_handler

# Exemple 1 : Identifier un pays
event = {
    "action": "identifier_pays",
    "params": {"pays": "Canada"}
}
response = api_handler(event)

# Exemple 2 : Analyser impact complet
event = {
    "action": "analyser_impact",
    "params": {
        "pays": "Canada",
        "date_debut": "01/01/2000",
        "date_fin": "31/12/2009",
        "trimestres_francais": 130,
        "duree_requise_taux_plein": 167
    }
}
response = api_handler(event)
```

### Actions disponibles
1. **identifier_pays** : Identifier statut d'un pays
2. **calculer_trimestres** : Calculer trimestres d'une période
3. **analyser_impact** : Analyse complète impact retraite
4. **calculer_pension** : Calculer pension avec prorata

---

## 📚 RÉFÉRENCES ET RESSOURCES

### Organismes officiels

**CLEISS** (Centre des Liaisons Européennes et Internationales)
- URL : www.cleiss.fr
- Rôle : Coordination internationale des régimes de sécurité sociale
- Ressources : Listes accords, formulaires, guides par pays

**CNAV** (Caisse Nationale d'Assurance Vieillesse)
- URL : www.lassuranceretraite.fr
- Rôle : Caisse de retraite française pour salariés du privé
- Ressources : Relevé de carrière, simulation retraite

---

### Bases légales

**Union Européenne** :
- Règlement CE 883/2004 (coordination régimes)
- Règlement CE 987/2009 (modalités d'application)

**Conventions bilatérales** :
- 41 conventions en vigueur avec dispositions vieillesse
- Consulter textes sur www.cleiss.fr/conventions

---

### Formulaires utiles

**UE/EEE/Suisse** :
- E205 : Certificat périodes d'assurance
- E207 : Certificat périodes de résidence
- E104 : Certificat durée totale d'assurance

**Conventions bilatérales** :
- Formulaires spécifiques selon convention
- Téléchargement : www.cleiss.fr/formulaires

---

## 🔄 INTÉGRATION AVEC AUTRES SKILLS

### Liens avec d'autres modules

**SKILL_analyse_releve_de_carriere** :
- Détection automatique mentions périodes étrangères
- Déclenchement du skill Trimestres Étranger si détecté

**SKILL_estimation_pensions_retraite** :
- Intégration coefficient prorata dans calcul pension base
- Mention explicite "deux pensions" dans le rapport

**SKILL_PROMPT2_rapport_client** :
- Section dédiée "Périodes à l'étranger"
- Tableau récapitulatif avec astérisque (*)
- Note de bas de page : "Sous réserve validation CNAV"

---

## ⚙️ FICHIERS ASSOCIÉS

### Script Python
**Fichier** : `calcul_trimestres_etranger.py` (version 2.0)
**Fonctions principales** :
- `identifier_statut_pays()` : Identifier catégorie pays
- `calculer_trimestres_periode()` : Convertir période en trimestres
- `analyser_impact_trimestres_etranger()` : Analyse complète
- `calculer_pension_avec_prorata_etranger()` : Calcul pension
- `api_handler()` : Handler N8N

### Règles de contrôle
**Fichier** : `trimestres_etranger_regles.json`
**Contenu** :
- 7 contrôles (TRE_C01 à TRE_C07)
- Workflow de validation en 4 étapes
- Token estimates détaillés
- Explications client standardisées

---

## 📝 CHECKLIST CONSULTATION

### ✅ Avant la consultation
- [ ] Client mentionne périodes à l'étranger
- [ ] Identifier pays concerné(s)
- [ ] Vérifier catégorie (UE/Convention/Sans accord)
- [ ] Rassembler dates exactes périodes

### ✅ Pendant l'analyse
- [ ] Utiliser `identifier_statut_pays()` pour chaque pays
- [ ] Calculer trimestres avec `calculer_trimestres_periode()`
- [ ] Analyser impact avec `analyser_impact_trimestres_etranger()`
- [ ] Vérifier tous les contrôles (TRE_C01 à TRE_C07)
- [ ] Pas d'alerte ROUGE non résolue

### ✅ Explication au client
- [ ] Expliquer principe "DEUX pensions"
- [ ] Détailler calcul au prorata français
- [ ] Mentionner pension étrangère (à calculer par pays concerné)
- [ ] Lister documents nécessaires (selon catégorie)
- [ ] Recommander délais (3-6 mois anticipation)

### ✅ Dans le rapport
- [ ] Section "Périodes à l'étranger" dédiée
- [ ] Tableau avec ligne spécifique + astérisque (*)
- [ ] Note : "Sous réserve validation CNAV"
- [ ] Liste documents à fournir
- [ ] Coordonnées CLEISS/CNAV

---

## 🆘 CAS PARTICULIERS

### Client avec plusieurs pays étrangers
- Traiter chaque pays séparément
- Appliquer règle max 4 trimestres/an GLOBAL
- Totaliser tous les trimestres étrangers pour taux plein
- Expliquer : "Vous recevrez X pensions" (1 France + N pays)

### Périodes chevauchantes France/Étranger
- Vérifier cumul d'activités
- Appliquer règle coordination : max 4 trim/an
- Alerter si > 4 trimestres détectés
- Documenter dans le rapport

### Pays sans accord + beaucoup de trimestres
- Expliquer clairement : trimestres NON comptabilisés
- Impact sur taux plein (risque décote)
- Recommander contact organisme pays étranger
- Proposer solutions : rachat VPLR si éligible

### Incertitude sur dates exactes
- Utiliser dates approximatives pour estimation
- Alerter niveau ORANGE (TRE_C04)
- Demander au client de vérifier/confirmer
- Mentionner "dates à confirmer" dans rapport

---

## 📈 AMÉLIORATIONS FUTURES (v3.0)

### En réflexion
- [ ] Intégration barèmes pensions étrangères (estimations)
- [ ] API CLEISS directe pour vérification automatique
- [ ] Calcul automatique pension étrangère (pays UE)
- [ ] Tests unitaires complets
- [ ] Gestion multi-devises

---

**Version** : 1.0  
**Date création** : 10/11/2025  
**Auteur** : EOR - Expertise Optimisation des Retraites  
**Fichiers associés** :
- `calcul_trimestres_etranger.py` (v2.0)
- `trimestres_etranger_regles.json` (v1.0)

**Maintenance** :
- Mise à jour annuelle : Liste pays avec accords
- Vérification : www.cleiss.fr/accords
- Nouvelle convention : Ajouter pays dans PAYS_CONVENTION_BILATERALE
