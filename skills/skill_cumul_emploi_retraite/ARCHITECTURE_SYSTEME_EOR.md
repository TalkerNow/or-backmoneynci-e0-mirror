# ARCHITECTURE SYSTÈME EOR - CALCULATEUR RETRAITE

## ⚠️ DOCUMENT À LIRE EN PREMIER POUR TOUTE TÂCHE

**Version** : 1.0  
**Date** : 2025-01-10  
**Statut** : RÉFÉRENCE ABSOLUE

---

## 🎯 PRINCIPE FONDAMENTAL

Le système "Calculateur Retraite" EOR est une **APPLICATION WEB** avec des **BOUTONS** qui déclenchent des calculs via **N8N**.

**❌ CE N'EST PAS** :
- Un chatbot conversationnel
- Un système avec déclencheurs de phrases
- Claude qui parle au client

**✅ C'EST** :
- Une interface web professionnelle
- Des boutons qui activent des calculs
- Des API N8N qui appellent des scripts Python

---

## 🏗️ ARCHITECTURE TECHNIQUE

```
┌─────────────────────────────────────────────────────────┐
│         INTERFACE WEB (Vue consultant)                   │
│                                                          │
│  [Bouton: Analyse Relevé Carrière]                      │
│  [Bouton: Éligibilité RACL]                             │
│  [Bouton: Cumul Emploi Retraite]                        │
│  [Bouton: Trimestres Étranger]                          │
│  [Bouton: Estimation Pensions]                          │
│  [Bouton: etc...]                                       │
│                                                          │
└──────────────────┬──────────────────────────────────────┘
                   │
                   ▼
         ┌─────────────────┐
         │      N8N        │ ← Orchestration
         │   (workflow)    │
         └────────┬────────┘
                  │
                  ▼
      ┌───────────────────────┐
      │ api_handler(params)   │ ← Point d'entrée Python
      │                       │
      │  • Validation params  │
      │  • Calculs           │
      │  • Contrôles         │
      │  • Alertes           │
      │  • Return JSON       │
      └───────────────────────┘
                  │
                  ▼
         ┌────────────────┐
         │  Résultat JSON  │
         └────────────────┘
                  │
                  ▼
         ┌────────────────┐
         │  Affichage Web  │
         └────────────────┘
```

---

## 📁 STRUCTURE D'UN SKILL

Chaque "skill" (module de calcul) se compose de **3 fichiers** :

### 1. SKILL_[nom].md (Documentation Markdown)
**Contenu** :
- ✅ Contexte réglementaire (lois, circulaires, dates)
- ✅ Principes fondamentaux
- ✅ Règles métier détaillées
- ✅ Contrôles de cohérence (avec IDs : XXX_C01, XXX_C02...)
- ✅ Signaux d'alerte (Rouge/Orange/Jaune)
- ✅ Exemples pratiques
- ✅ Valeurs réglementaires (année en cours)
- ✅ Sources documentaires

**IMPORTANT** :
- ❌ **PAS de section "Déclencheurs"** (pas de phrases conversationnelles)
- ❌ **PAS de section "Utilisation dans Claude"**
- ❌ **PAS de mentions de chatbot**

**Format** : "Classe mondiale" = documentation complète, citations exactes, exemples concrets

### 2. [nom]_regles.json (Structured Data)
**Contenu** :
```json
{
  "skill_info": {...},
  "regles_metier": {...},
  "controles_coherence": [
    {
      "id": "XXX_C01",
      "libelle": "...",
      "condition": "...",
      "type": "ERREUR_CRITIQUE",
      "couleur": "ROUGE",
      "message": "...",
      "actions_correctives": [...]
    }
  ],
  "alertes": {
    "rouge": [...],
    "orange": [...],
    "jaune": [...]
  },
  "valeurs_reglementaires_[annee]": {...},
  "exemples_calcul": [...]
}
```

**Utilité** : Données structurées exploitables par Python et N8N

### 3. calcul_[nom].py (Script Python)
**Contenu** :
```python
def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour N8N
    
    Params: Dict avec données client
    Returns: Dict avec résultats + contrôles + alertes
    """
    # Validation
    # Calculs
    # Contrôles de cohérence
    # Génération alertes
    # Return JSON
```

**IMPORTANT** :
- ✅ **Toujours** une fonction `api_handler(params)` comme point d'entrée
- ✅ Retour JSON structuré
- ✅ Estimation tokens dans le retour
- ✅ Gestion d'erreurs propre

---

## 🔄 WORKFLOW TYPE

### Exemple : Calcul CER (Cumul Emploi Retraite)

1. **Consultant** ouvre le dossier client dans l'interface web
2. **Consultant** remplit les champs (date naissance, trimestres, etc.)
3. **Consultant** clique sur [Bouton: Cumul Emploi Retraite]
4. **Interface** envoie les données à N8N
5. **N8N** appelle `api_handler(params)` du script `calcul_cumul_emploi_retraite.py`
6. **Script Python** :
   - Valide les données
   - Détermine type cumul (TOTAL / PLAFONNÉ)
   - Calcule plafond si nécessaire
   - Vérifie délai 6 mois
   - Exécute contrôles de cohérence
   - Génère alertes
   - Retourne JSON
7. **N8N** reçoit le JSON
8. **Interface** affiche le résultat au consultant

**Durée totale** : < 2 secondes

---

## ❌ CE QUI N'EXISTE PAS

- **Pas de chatbot** avec le client final
- **Pas de conversation** avec le client
- **Pas de déclencheurs** type "carrière longue" ou "cumul emploi retraite"
- **Pas de génération automatique** de document Word par les skills
  - (Le Word est généré par PROMPT 2 dans un workflow séparé)

---

## ✅ CE QUI EXISTE

### Skills actuels (confirmés)
1. ✅ **RACL** (Retraite Anticipée Carrière Longue)
2. ✅ **Trimestres Étranger**
3. ✅ **Analyse Relevé Carrière** (via PROMPT 1)
4. ✅ **Retraite Progressive**
5. ✅ **Chômage**
6. ✅ **Cumul Emploi Retraite** (en cours)

### Workflows de consultation
- **PROMPT 1** : Analyse relevé carrière (avant RDV)
  - Upload PDF relevé → Analyse complète
  - Résultat : Synthèse pré-entretien
  
- **PROMPT 2** : Document Word client (après RDV)
  - Intègre NOTA + commentaires entretien
  - Génère Word avec branding EOR
  - Résultat : Document remise client

**Note** : PROMPT 1 et 2 sont SÉPARÉS des skills N8N

---

## 🎨 CONVENTIONS DE NOMMAGE

### Fichiers
- `SKILL_[nom].md` (ex: SKILL_cumul_emploi_retraite.md)
- `[nom]_regles.json` (ex: cumul_emploi_retraite_regles.json)
- `calcul_[nom].py` (ex: calcul_cumul_emploi_retraite.py)

### Contrôles de cohérence
- Format : `XXX_C01`, `XXX_C02`, etc.
- XXX = Code skill (ex: CER pour Cumul Emploi Retraite)
- C = Contrôle
- 01, 02... = Numéro séquentiel

### Alertes
- Format : `XXX_A01`, `XXX_A02`, etc.
- XXX = Code skill
- A = Alerte
- 01, 02... = Numéro séquentiel

---

## 🎯 CHECKLIST AVANT CRÉATION D'UN NOUVEAU SKILL

Avant de créer un nouveau skill, VÉRIFIER :

- [ ] **Interface** : Web avec boutons (PAS de chatbot)
- [ ] **Pas de déclencheurs** conversationnels
- [ ] **API handler** : Point d'entrée N8N
- [ ] **3 fichiers** : .md + .json + .py
- [ ] **Format "Classe mondiale"** : Doc complète avec citations exactes
- [ ] **Contrôles de cohérence** : IDs préfixés (XXX_C01...)
- [ ] **Alertes** : Rouge/Orange/Jaune avec IDs (XXX_A01...)
- [ ] **Pas de rapport Word** dans le skill (Word = PROMPT 2 séparé)
- [ ] **Valeurs année en cours** intégrées
- [ ] **Sources réglementaires** citées

---

## 📊 PUBLICS UTILISATEURS

### 1. Consultants EOR (utilisateurs finaux)
- Utilisent l'interface web
- Cliquent sur les boutons
- Voient les résultats à l'écran
- Peuvent générer des rapports Word (via PROMPT 2)

### 2. Développeurs (intégration)
- Intègrent les skills dans N8N
- Connectent les boutons aux api_handler
- Gèrent les erreurs

### 3. Jeff (gouvernance)
- Définit les règles métier
- Valide la conformité réglementaire
- Teste les calculs
- Approuve les livrables

---

## 🔧 MAINTENANCE

### Mise à jour annuelle (janvier)
- ✅ Valeurs réglementaires (SMIC, plafonds, etc.)
- ✅ Durées d'assurance si réforme
- ✅ Valeurs de points (ARRCO, IRCANTEC, RCI)
- ✅ Circulaires CNAV de l'année

### Mise à jour réglementaire (au fil de l'eau)
- ✅ Nouvelles lois
- ✅ Nouveaux décrets
- ✅ Nouvelles circulaires CNAV
- ✅ Changements de règles

---

## 📞 SUPPORT ET QUESTIONS

**Pour toute nouvelle tâche** :
1. Lire CE document EN PREMIER
2. Vérifier la checklist
3. Poser des questions SI ET SEULEMENT SI quelque chose n'est pas clair
4. Ne PAS supposer que c'est un chatbot
5. Ne PAS ajouter de déclencheurs conversationnels

---

## 🚨 ERREURS FRÉQUENTES À ÉVITER

1. ❌ Ajouter une section "Déclencheurs" dans le SKILL.md
2. ❌ Parler de "conversation" avec le client
3. ❌ Créer un chatbot conversationnel
4. ❌ Oublier le `api_handler(params)` dans le .py
5. ❌ Mélanger les workflows (skills N8N ≠ PROMPT 1/2)
6. ❌ Créer un rapport Word dans le skill
7. ❌ Oublier les contrôles de cohérence
8. ❌ Oublier les alertes Rouge/Orange/Jaune

---

## 💡 PHILOSOPHIE

**"Si c'est pas bordé, c'est de la merde"** - Jeff

Chaque skill doit être :
- ✅ Complet (documentation exhaustive)
- ✅ Précis (citations exactes des circulaires)
- ✅ Contrôlé (8+ contrôles de cohérence)
- ✅ Alerté (système d'alertes 3 niveaux)
- ✅ Testé (exemples pratiques)
- ✅ Conforme (sources réglementaires)
- ✅ Professionnel (qualité "classe mondiale")

**Pas de demi-mesure. Tout doit être parfait.**

---

**Dernière mise à jour** : 2025-01-10  
**Auteur** : Claude + Jeff  
**Statut** : RÉFÉRENCE ABSOLUE - À LIRE AVANT TOUTE TÂCHE
