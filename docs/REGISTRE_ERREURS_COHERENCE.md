# 📚 REGISTRE DES ERREURS DE COHÉRENCE
*Système d'auto-apprentissage - Chaque erreur capturée devient une règle Gate #2*

---

## 📊 STATISTIQUES

- **Total erreurs capturées** : 9
- **Règles actives** : 9
- **Règles archivées** : 0
- **Dernière mise à jour** : 06/04/2026

---

## 🔴 RÈGLES ACTIVES (GATE #2)

### R001 | Trimestres enfants attribués à un homme
**Date d'ajout** : 06/11/2025  
**Cas origine** : M. Dupont  
**Prompt concerné** : PROMPT 1 + PROMPT 2  
**Consultant** : Système initial  
**Erreur détectée** : Attribution de 8 trimestres pour enfants à un homme  
**Condition Python** : `sexe == "H" and trimestres_enfants > 0`  
**Message d'erreur** : "❌ ERREUR CRITIQUE : Impossible d'attribuer des trimestres pour enfants à un homme. Les trimestres pour enfants sont réservés aux femmes."  
**Niveau** : 🔴 CRITIQUE (bloquant)  
**Statut** : ✅ ACTIF  

**Impact** : Bloque automatiquement tout calcul qui attribuerait des majorations enfants à un homme.

---

### R002 | Âge légal inférieur à 62 ans
**Date d'ajout** : 06/11/2025  
**Cas origine** : Système initial  
**Prompt concerné** : PROMPT 1 + PROMPT 2  
**Consultant** : Système initial  
**Erreur détectée** : Âge légal calculé à 61 ans (impossible depuis réforme 2023)  
**Condition Python** : `age_legal < 62`  
**Message d'erreur** : "❌ ERREUR CRITIQUE : Âge légal inférieur à 62 ans impossible. Depuis la réforme 2023, l'âge légal minimum est de 62 ans. Vérifier les calculs."  
**Niveau** : 🔴 CRITIQUE (bloquant)  
**Statut** : ✅ ACTIF  

**Impact** : Empêche les estimations avec un âge légal incohérent.

---

### R003 | Nombre de trimestres supérieur à 200
**Date d'ajout** : 06/11/2025  
**Cas origine** : Système initial  
**Prompt concerné** : PROMPT 1 + PROMPT 2  
**Consultant** : Système initial  
**Erreur détectée** : Plus de 200 trimestres validés (impossible : max 50 ans de carrière)  
**Condition Python** : `trimestres_total > 200`  
**Message d'erreur** : "❌ ERREUR CRITIQUE : Plus de 200 trimestres impossible. Maximum théorique = 50 ans × 4 trimestres = 200 trimestres. Vérifier le relevé de carrière."  
**Niveau** : 🔴 CRITIQUE (bloquant)  
**Statut** : ✅ ACTIF  

**Impact** : Détecte les erreurs de saisie ou de calcul de trimestres.

---

### R004 | Enfant né avant le client
**Date d'ajout** : 06/11/2025  
**Cas origine** : Système initial  
**Prompt concerné** : PROMPT 1 + PROMPT 2  
**Consultant** : Système initial  
**Erreur détectée** : Date de naissance enfant antérieure à la date de naissance du client  
**Condition Python** : `date_naissance_enfant < date_naissance_client`  
**Message d'erreur** : "❌ ERREUR CRITIQUE : Enfant né avant le client. Vérifier les dates de naissance."  
**Niveau** : 🔴 CRITIQUE (bloquant)  
**Statut** : ✅ ACTIF  

**Impact** : Empêche les incohérences temporelles dans les données familiales.

---

---

### R005 | Anachronisme PASS — plafond SS historique incorrect
**Date d'ajout** : 06/04/2026
**Cas origine** : Kreft / Boussaha
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système — rapatrié depuis archives GEM
**Erreur détectée** : PASS 2024/2025 appliqué sur des salaires historiques (ex : 2006) → SAM surévalué artificellement
**Condition Python** : `pass_utilise != HISTORIQUE_PASS[annee]` → utilisation d'un PASS non contemporain de l'année
**Message d'erreur** : "❌ ERREUR CRITIQUE : Le plafond SS utilisé pour l'année [N] ne correspond pas au PASS historique de cette année. Utiliser HISTORIQUE_PASS[N] = [valeur]. Ne jamais appliquer le PASS actuel sur des années antérieures."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Chaque année mal plafonnée peut injecter des milliers d'euros de "faux droits" dans le SAM. Faute technique majeure sur le montant final de pension.

---

### R006 | Oubli "Bouclier 67 ans" — arbitrage décote CNAV
**Date d'ajout** : 06/04/2026
**Cas origine** : Kreft / Boussaha
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système — rapatrié depuis archives GEM
**Erreur détectée** : Décote calculée sur le déficit de durée brut (ex : 39 trimestres) sans appliquer l'arbitrage légal Art. R351-27 CSS : retenir le MINIMUM entre déficit durée et déficit d'âge jusqu'à 67 ans
**Condition Python** : `nb_trim_decote != min(manquants_duree, (67 - age_depart_ans) * 4)`
**Message d'erreur** : "❌ ERREUR CRITIQUE : Décote calculée sans arbitrage 'Bouclier 67 ans'. La loi impose de retenir Min(trimestres manquants durée, trimestres manquants jusqu'à 67 ans). Résultat actuel potentiellement trop pénalisant."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Sous-évalue la pension de plusieurs points de % → conseil client erroné sur la date de départ. Ex : 42,5% au lieu de 37,5% soit +5 points de pension sur le cas Kreft.

---

### R007 | Décote CNAV et AGIRC-ARRCO non synchronisées
**Date d'ajout** : 06/04/2026
**Cas origine** : Kreft / Boussaha
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système — rapatrié depuis archives GEM
**Erreur détectée** : Le nombre de trimestres manquants utilisé pour la décote CNAV et pour le coefficient AGIRC-ARRCO divergent. La loi impose d'utiliser le même nb de trimestres (après arbitrage R006) pour les deux régimes.
**Condition Python** : `trim_decote_cnav != trim_decote_agirc_arrco`
**Message d'erreur** : "❌ ERREUR CRITIQUE : Le nombre de trimestres manquants appliqué à la décote CNAV ([N]) diffère de celui utilisé pour le coefficient AGIRC-ARRCO ([M]). Ces deux valeurs doivent être strictement identiques."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Incohérence entre pension base et pension complémentaire → livrable juridiquement contestable.

---

### R008 | Dilution SAM — années futures sous-performantes incluses dans le Top 25
**Date d'ajout** : 06/04/2026
**Cas origine** : Kreft / Boussaha
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système — rapatrié depuis archives GEM
**Erreur détectée** : Des années futures simulées à faible revenu (ex : 6 000 €) intégrées dans le Top 25 SAM alors qu'elles sont inférieures à la 25e meilleure année historique revalorisée → SAM sous-évalué
**Condition Python** : `min(top25_sam) > revenu_futur_simule` → l'année future ne devrait pas être dans le Top 25
**Message d'erreur** : "❌ ERREUR CRITIQUE : Une ou plusieurs années futures (revenu [X] €) ont été incluses dans le calcul du SAM mais sont inférieures à la 25e meilleure année historique revalorisée ([Y] €). Ces années doivent être exclues du SAM (elles comptent pour la durée, pas pour le montant)."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : SAM sous-évalué → pension de base calculée en-dessous du réel → conseil conservateur mais inexact.

---

### R009 | Prix d'achat du point AGIRC-ARRCO non contemporain
**Date d'ajout** : 06/04/2026
**Cas origine** : Kreft / Boussaha
**Prompt concerné** : PROMPT 1 + PROMPT 2
**Consultant** : Système — rapatrié depuis archives GEM
**Erreur détectée** : Prix d'achat du point 2023 (~18,60 €) ou 2024 (19,6321 €) utilisé au lieu du prix 2025 (20,1877 €) pour calculer les points acquis → nb de points surestimé
**Condition Python** : `prix_achat_point_utilise != PRIX_ACHAT_POINT_AA_2025` pour les projections futures
**Message d'erreur** : "❌ ERREUR CRITIQUE : Le prix d'achat du point AGIRC-ARRCO utilisé ([X] €) ne correspond pas à la valeur 2025 (20,1877 €). Toute projection future doit utiliser PRIX_ACHAT_POINT_AA_2025 = 20,1877 €."
**Niveau** : 🔴 CRITIQUE (bloquant)
**Statut** : ✅ ACTIF

**Impact** : Nombre de points surestimé → pension AGIRC-ARRCO gonflée → promesse client non tenue.

---

## 📋 TEMPLATE POUR AJOUTER UNE NOUVELLE RÈGLE

**Copier-coller ce template lors de l'ajout d'une nouvelle erreur** :

```markdown
### RXXX | [Titre court et descriptif de l'erreur]
**Date d'ajout** : JJ/MM/AAAA  
**Cas origine** : [Nom anonymisé du client ou numéro dossier]  
**Prompt concerné** : PROMPT 1 / PROMPT 2 / PROMPT 3  
**Consultant** : [Prénom ou initiales]  
**Erreur détectée** : [Description détaillée de ce qui s'est passé]  
**Condition Python** : `[expression Python qui détecte l'erreur]`  
**Message d'erreur** : "[Message clair à afficher au consultant]"  
**Niveau** : 🔴 CRITIQUE / 🟠 AVERTISSEMENT  
**Statut** : ✅ ACTIF  

**Impact** : [Explication de l'effet de cette règle]
```

---

## 🟢 RÈGLES ARCHIVÉES

*Aucune règle archivée pour le moment*

*(Les règles sont archivées quand elles deviennent obsolètes ou sont remplacées par des règles plus précises)*

---

## 📖 GUIDE D'UTILISATION

### Pour les consultants : Comment signaler une erreur

**Phrases déclencheurs** (Claude détectera automatiquement) :
- "Cette erreur ne doit plus se reproduire"
- "Bloquer cette erreur"
- "Ajouter règle Gate #2"
- "PROMPT [1/2/3] erreur : [description]"
- "Nouvelle règle de cohérence"

**Exemple simple** :
```
Consultant : "PROMPT 2 erreur : Le SAM calculé est de 250 000€, c'est impossible. 
Le maximum légal est 3× le plafond SS soit environ 140k€. 
Cette erreur ne doit plus se reproduire."

→ Claude capturera automatiquement l'erreur et créera la règle R005
```

### Pour Claude : Workflow automatique

Quand une phrase déclencheur est détectée :
1. ✅ Déclencher le SKILL_PROMPT3_apprentissage_erreurs.md
2. ✅ Poser les 4 questions obligatoires
3. ✅ Générer la condition Python
4. ✅ Ajouter au registre (fichier actuel)
5. ✅ Mettre à jour validation_autocontrole.py
6. ✅ Confirmer l'ajout

---

## 🎯 OBJECTIFS DU SYSTÈME

- **Zéro récurrence** : Une erreur capturée ne se reproduit jamais
- **Traçabilité** : Historique complet avec cas d'origine
- **Évolutivité** : Facile d'ajouter de nouvelles règles
- **Transparence** : Consultants peuvent consulter toutes les règles actives

---

## 📞 MAINTENANCE

**Révision mensuelle** : Analyser les règles pour détecter :
- Règles jamais déclenchées (peut-être trop spécifiques)
- Règles déclenchées trop souvent (peut-être trop strictes)
- Règles obsolètes (changement réglementaire)

**Contact** : En cas de question sur une règle, vérifier le "Cas origine" et le "Consultant" pour comprendre le contexte.

---

**Version** : 1.1
**Dernière mise à jour** : 06/04/2026
**Prochaine révision** : 06/05/2026

---

## 📜 CHANGELOG

| Version | Date | Modification |
|---------|------|--------------|
| 1.1 | 06/04/2026 | Ajout R005→R009 — rapatriement archives GEM dossier Kreft/Boussaha (déc. 2025). 5 erreurs de calcul critique : anachronisme PASS, bouclier 67 ans, synchro décote, dilution SAM, prix d'achat point AA. |
| 1.0 | 06/11/2025 | Création initiale — R001→R004 (cohérence données de base) |
