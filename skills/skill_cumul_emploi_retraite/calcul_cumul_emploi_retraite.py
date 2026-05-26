"""
========================================
SKILL : Cumul Emploi Retraite (CER)
========================================

Version : 1.0
Date : 2025-01
Conformité : Circulaire CNAV 2017-41 du 12/12/2017

Fonctions principales :
- Détermination type cumul (TOTAL vs PLAFONNÉ)
- Calcul plafond cumul (moyenne salaires ou 1,6 SMIC)
- Calcul écrêtement si dépassement
- Vérification délai 6 mois dernier employeur
- Contrôles de cohérence (CER_C01 à CER_C08)
- Alertes Rouge/Orange/Jaune

Point d'entrée N8N : api_handler(params)
"""

from typing import Dict, List, Optional, Tuple
from datetime import datetime, timedelta
from dateutil.relativedelta import relativedelta
import json
from decimal import Decimal, ROUND_HALF_UP

# ============================================================================
# CHARGEMENT DES RÈGLES
# ============================================================================

with open('cumul_emploi_retraite_regles.json', 'r', encoding='utf-8') as f:
    REGLES = json.load(f)

VALEURS_2025 = REGLES['valeurs_reglementaires_2025']
CONTROLES = REGLES['controles_coherence']
ALERTES = REGLES['alertes']

# ============================================================================
# CONSTANTES 2025
# ============================================================================

AGE_LEGAL = VALEURS_2025['ages']['age_legal_generation_1955_plus']  # 62 ans
AGE_TAUX_PLEIN_AUTO = VALEURS_2025['ages']['age_taux_plein_auto_generation_1955_plus']  # 67 ans
SMIC_HORAIRE_2025 = Decimal(str(VALEURS_2025['smic_2025']['smic_horaire_brut']))  # 11.88€
COEFFICIENT_SMIC = Decimal('1.6')
HEURES_ANNUELLES = Decimal('1820')
PLAFOND_SMIC_2025 = Decimal(str(VALEURS_2025['smic_2025']['plafond_mensuel_brut']))  # 2873.76€
DELAI_DECLARATION_JOURS = 30
DELAI_DERNIER_EMPLOYEUR_MOIS = 6

# Durées d'assurance pour taux plein par génération
# Source : Circulaire Cnav 2026-07 du 05/03/2026 (loi n°2025-1403 du 30/12/2025 —
# suspension de la réforme 2023). Effet retraite ≥ 01/09/2026.
DUREES_TAUX_PLEIN = {
    (1958, 1960): 167,
    (1961, 1961, 1, 8): 168,
    (1961, 1962, 9, 12): 169,
    (1963, 1964): 170,
    (1965, 1965, 1, 3): 170,   # Janvier-Mars 1965
    (1965, 1965, 4, 12): 171,  # Avril-Décembre 1965
    (1966, 2100): 172  # 1966 et après
}

# ============================================================================
# API HANDLER - POINT D'ENTRÉE N8N
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour N8N
    
    Params attendus:
    {
        "date_naissance": "1960-05-15",
        "date_effet_retraite": "2025-05-01",
        "duree_assurance_trimestres": 172,
        "retraites_liquidees": ["RG", "ARRCO"],
        "retraites_non_liquidees": [],  # Optionnel
        "reprise_activite": {  # Optionnel
            "date": "2025-06-01",
            "employeur": "Entreprise_X",
            "dernier_employeur": true,
            "date_fin_contrat_dernier_employeur": "2025-02-28",  # Si dernier_employeur = true
            "revenus_mensuels_bruts": 2000
        },
        "periode_reference": {  # Pour CER PLAFONNÉ
            "salaires_bruts": [2100, 2100, 2100]  # 3 derniers mois
        },
        "pensions_mensuelles": {  # Pour CER PLAFONNÉ
            "RG": 1200,
            "SNCF": 0,
            "complementaires": 800
        },
        "regimes_ages_ouverture": {  # Optionnel pour exception subsidiarité
            "Regime_B": 65
        }
    }
    
    Returns:
    {
        "type_cumul": "CER_TOTAL" | "CER_PLAFONNE",
        "autorise": true/false,
        "plafond": 2873.76,  # Si CER_PLAFONNE
        "depassement": 0,
        "ecretement": {...},  # Si dépassement
        "suspension": {...},  # Si délai 6 mois non respecté
        "controles": [...],
        "alertes": [...],
        "date_fin_restriction": "2025-11-01",  # Si suspension
        "resume": "...",
        "tokens_estimes": 1500
    }
    """
    
    try:
        # 1. Validation et extraction des données
        date_naissance = datetime.fromisoformat(params['date_naissance'])
        date_effet_retraite = datetime.fromisoformat(params['date_effet_retraite'])
        duree_trimestres = params['duree_assurance_trimestres']
        retraites_liquidees = params['retraites_liquidees']
        retraites_non_liquidees = params.get('retraites_non_liquidees', [])
        regimes_ages_ouverture = params.get('regimes_ages_ouverture', {})
        
        # 2. Détermination type cumul
        type_cumul, motif_plafonne = determiner_type_cumul(
            date_naissance,
            date_effet_retraite,
            duree_trimestres,
            retraites_liquidees,
            retraites_non_liquidees,
            regimes_ages_ouverture
        )
        
        # 3. Exécution contrôles de cohérence
        controles = executer_controles_coherence(params, type_cumul)
        
        # 4. Génération alertes
        alertes = generer_alertes(controles, params, type_cumul)
        
        # 5. Traitement selon type
        if type_cumul == "CER_TOTAL":
            resultat = traiter_cer_total(params, controles, alertes)
        else:
            resultat = traiter_cer_plafonne(params, controles, alertes, motif_plafonne)
        
        # 6. Estimation tokens
        resultat['tokens_estimes'] = estimer_tokens(resultat)
        
        return resultat
        
    except Exception as e:
        return {
            "erreur": True,
            "message": f"Erreur lors du traitement : {str(e)}",
            "type": "erreur_technique",
            "tokens_estimes": 100
        }

# ============================================================================
# DÉTERMINATION TYPE CUMUL
# ============================================================================

def determiner_type_cumul(
    date_naissance: datetime,
    date_effet_retraite: datetime,
    duree_trimestres: int,
    retraites_liquidees: List[str],
    retraites_non_liquidees: List[str],
    regimes_ages_ouverture: Dict[str, int]
) -> Tuple[str, Optional[str]]:
    """
    Détermine si CER TOTAL ou CER PLAFONNÉ
    
    Returns:
        (type_cumul, motif_plafonne)
        - type_cumul: "CER_TOTAL" ou "CER_PLAFONNE"
        - motif_plafonne: None si CER_TOTAL, sinon raison du plafonnement
    """
    
    age_a_depart = calculer_age(date_naissance, date_effet_retraite)
    duree_requise = obtenir_duree_taux_plein(date_naissance.year)
    
    # Condition 1: Subsidiarité
    subsidiarite_ok, exception_accordee = verifier_subsidiarite(
        retraites_non_liquidees,
        regimes_ages_ouverture,
        age_a_depart
    )
    
    if not subsidiarite_ok and not exception_accordee:
        return "CER_PLAFONNE", "subsidiarite_non_respectee"
    
    # Condition 2: Âge et durée (Option A ou Option B)
    taux_plein_atteint = duree_trimestres >= duree_requise
    
    # Option A: Âge légal + Taux plein
    condition_a = age_a_depart >= AGE_LEGAL and taux_plein_atteint
    
    # Option B: Âge taux plein automatique
    condition_b = age_a_depart >= AGE_TAUX_PLEIN_AUTO
    
    if condition_a or condition_b:
        if exception_accordee:
            # CER TOTAL temporaire avec limite d'âge
            return "CER_TOTAL", None
        else:
            return "CER_TOTAL", None
    else:
        # Déterminer la raison du plafonnement
        if age_a_depart < AGE_LEGAL:
            motif = "age_legal_non_atteint"
        elif not taux_plein_atteint:
            motif = "taux_plein_non_atteint"
        else:
            motif = "conditions_non_remplies"
        
        return "CER_PLAFONNE", motif

def verifier_subsidiarite(
    retraites_non_liquidees: List[str],
    regimes_ages_ouverture: Dict[str, int],
    age_actuel: float
) -> Tuple[bool, bool]:
    """
    Vérifie la condition de subsidiarité
    
    Returns:
        (subsidiarite_ok, exception_accordee)
    """
    
    if not retraites_non_liquidees:
        return True, False
    
    # Vérifier si exception possible (âge ouverture > âge légal)
    for regime in retraites_non_liquidees:
        if regime in regimes_ages_ouverture:
            age_ouverture = regimes_ages_ouverture[regime]
            if age_ouverture > AGE_LEGAL and age_actuel < age_ouverture:
                # Exception accordée jusqu'à l'âge d'ouverture
                return False, True
    
    return False, False

def calculer_age(date_naissance: datetime, date_reference: datetime) -> float:
    """Calcule l'âge en années avec décimales"""
    delta = relativedelta(date_reference, date_naissance)
    return delta.years + delta.months / 12.0

def obtenir_duree_taux_plein(annee_naissance: int) -> int:
    """Retourne la durée d'assurance requise pour le taux plein"""
    
    for cle, duree in DUREES_TAUX_PLEIN.items():
        if len(cle) == 2:  # Plage d'années
            debut, fin = cle
            if debut <= annee_naissance <= fin:
                return duree
        elif len(cle) == 4:  # Année + mois
            annee_debut, annee_fin, mois_debut, mois_fin = cle
            if annee_debut <= annee_naissance <= annee_fin:
                return duree
    
    return 172  # Valeur par défaut (génération 1965+)

# ============================================================================
# TRAITEMENT CER TOTAL
# ============================================================================

def traiter_cer_total(
    params: Dict,
    controles: List[Dict],
    alertes: List[Dict]
) -> Dict:
    """Traitement du cumul emploi retraite TOTAL"""
    
    # Vérifier si erreurs bloquantes
    erreurs_critiques = [c for c in controles if c['statut'] == 'ERREUR']
    
    if erreurs_critiques:
        autorise = False
    else:
        autorise = True
    
    resultat = {
        "type_cumul": "CER_TOTAL",
        "autorise": autorise,
        "restrictions": None,
        "reprise_immediate": autorise,
        "delai_attente": 0,
        "declaration_obligatoire": True,
        "motif_declaration": "controle_condition_subsidiarite",
        "limite_revenus": False,
        "plafond": None,
        "controles": controles,
        "alertes": alertes,
        "resume": generer_resume_cer_total(autorise, erreurs_critiques)
    }
    
    return resultat

def generer_resume_cer_total(autorise: bool, erreurs: List[Dict]) -> str:
    """Génère un résumé textuel pour CER TOTAL"""
    
    if autorise:
        return ("Cumul Emploi Retraite TOTAL autorisé. "
                "Reprise d'activité immédiate possible sans aucune restriction de revenus. "
                "Obligation de déclaration à la caisse pour contrôle de la subsidiarité.")
    else:
        messages_erreurs = [e['message'] for e in erreurs]
        return (f"Cumul Emploi Retraite TOTAL REFUSÉ. "
                f"Raisons : {' ; '.join(messages_erreurs)}. "
                f"Application du régime CER PLAFONNÉ.")

# ============================================================================
# TRAITEMENT CER PLAFONNÉ
# ============================================================================

def traiter_cer_plafonne(
    params: Dict,
    controles: List[Dict],
    alertes: List[Dict],
    motif_plafonne: str
) -> Dict:
    """Traitement du cumul emploi retraite PLAFONNÉ"""
    
    reprise = params.get('reprise_activite')
    
    if not reprise:
        return {
            "type_cumul": "CER_PLAFONNE",
            "autorise": True,
            "message": "Pas de reprise d'activité déclarée",
            "motif_plafonne": motif_plafonne,
            "controles": controles,
            "alertes": alertes,
            "resume": "CER PLAFONNÉ applicable. Aucune reprise d'activité actuellement déclarée."
        }
    
    # 1. Vérification délai 6 mois dernier employeur
    suspension = None
    if reprise.get('dernier_employeur', False):
        suspension = verifier_delai_dernier_employeur(
            params['date_effet_retraite'],
            reprise['date'],
            reprise.get('date_fin_contrat_dernier_employeur')
        )
    
    if suspension:
        # Ajout contrôle et alerte suspension
        controles.append({
            "id": "CER_C04",
            "statut": "ERREUR",
            "message": f"Délai 6 mois non respecté : suspension du {suspension['date_debut']} au {suspension['date_fin']}"
        })
        alertes.append({
            "code": "CER_A03",
            "niveau": "ROUGE",
            "message": "SUSPENSION obligatoire (reprise chez dernier employeur < 6 mois)"
        })
        
        return {
            "type_cumul": "CER_PLAFONNE",
            "autorise": False,
            "motif_refus": "delai_6_mois_non_respecte",
            "suspension": suspension,
            "controles": controles,
            "alertes": alertes,
            "resume": generer_resume_suspension(suspension)
        }
    
    # 2. Calcul du plafond
    periode_ref = params.get('periode_reference', {})
    plafond = calculer_plafond(periode_ref.get('salaires_bruts', []))
    
    # 3. Calcul total revenus + pensions
    pensions = params.get('pensions_mensuelles', {})
    total_pensions = sum(Decimal(str(v)) for v in pensions.values())
    revenus_activite = Decimal(str(reprise.get('revenus_mensuels_bruts', 0)))
    total_revenus_pensions = revenus_activite + total_pensions
    
    # 4. Comparaison et écrêtement
    depassement = max(Decimal('0'), total_revenus_pensions - plafond)
    
    if depassement > 0:
        ecretement = calculer_ecretement(depassement, pensions)
        
        controles.append({
            "id": "CER_C06",
            "statut": "ALERTE",
            "message": f"Dépassement plafond : {float(depassement):.2f}€"
        })
        alertes.append({
            "code": "CER_A10",
            "niveau": "JAUNE",
            "message": "Écrêtement à appliquer"
        })
    else:
        ecretement = None
        controles.append({
            "id": "CER_C06",
            "statut": "OK",
            "message": "Plafond respecté"
        })
    
    resultat = {
        "type_cumul": "CER_PLAFONNE",
        "autorise": True,
        "motif_plafonne": motif_plafonne,
        "plafond": float(plafond),
        "revenus_activite": float(revenus_activite),
        "total_pensions": float(total_pensions),
        "total_revenus_pensions": float(total_revenus_pensions),
        "depassement": float(depassement),
        "ecretement": ecretement,
        "declaration_obligatoire": True,
        "delai_declaration_jours": DELAI_DECLARATION_JOURS,
        "controles": controles,
        "alertes": alertes,
        "resume": generer_resume_cer_plafonne(
            plafond, total_revenus_pensions, depassement, ecretement
        )
    }
    
    return resultat

# ============================================================================
# CALCULS PLAFOND ET ÉCRÊTEMENT
# ============================================================================

def calculer_plafond(salaires_periode_ref: List[float]) -> Decimal:
    """
    Calcule le plafond de cumul
    MAX(moyenne derniers salaires, 1.6 SMIC)
    """
    
    # Méthode A: Moyenne derniers salaires
    if salaires_periode_ref:
        nb_mois = len(salaires_periode_ref)
        total_salaires = sum(Decimal(str(s)) for s in salaires_periode_ref)
        moyenne_salaires = total_salaires / nb_mois
    else:
        moyenne_salaires = Decimal('0')
    
    # Méthode B: 1,6 SMIC (déjà calculé)
    plafond_smic = PLAFOND_SMIC_2025
    
    # Retourner le maximum
    plafond = max(moyenne_salaires, plafond_smic)
    
    return plafond.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP)

def calculer_ecretement(
    depassement: Decimal,
    pensions: Dict[str, float]
) -> Dict:
    """
    Calcule l'écrêtement à appliquer aux pensions
    Réduction = montant du dépassement
    Appliqué à chaque pension de base (RG, SNCF, MSA, etc.)
    """
    
    # Séparer pensions de base et complémentaires
    pensions_base = {}
    for regime, montant in pensions.items():
        if regime.lower() not in ['complementaires', 'arrco', 'agirc', 'ircantec']:
            pensions_base[regime] = Decimal(str(montant))
    
    ecretement_detail = {}
    
    for regime, montant_initial in pensions_base.items():
        reduction = min(depassement, montant_initial)
        montant_verse = max(Decimal('0'), montant_initial - reduction)
        
        ecretement_detail[regime] = {
            "montant_initial": float(montant_initial),
            "reduction": float(reduction),
            "montant_verse": float(montant_verse),
            "suspendu": montant_verse == 0
        }
    
    return ecretement_detail

def verifier_delai_dernier_employeur(
    date_effet_retraite: str,
    date_reprise: str,
    date_fin_contrat: Optional[str] = None
) -> Optional[Dict]:
    """
    Vérifie le respect du délai de 6 mois
    
    Returns:
        Dict avec infos suspension si délai non respecté, None sinon
    """
    
    date_effet = datetime.fromisoformat(date_effet_retraite)
    date_rep = datetime.fromisoformat(date_reprise)
    
    # Calculer le délai
    delta = relativedelta(date_rep, date_effet)
    delai_mois = delta.years * 12 + delta.months
    
    if delai_mois < DELAI_DERNIER_EMPLOYEUR_MOIS:
        # Calculer la date de fin de suspension
        date_fin_suspension = date_effet + relativedelta(months=6)
        date_fin_suspension = date_fin_suspension.replace(day=1) - timedelta(days=1)  # Dernier jour du 6ème mois
        
        return {
            "motif": "reprise_dernier_employeur_avant_6_mois",
            "date_debut": date_rep.strftime('%Y-%m-%d'),
            "date_fin": date_fin_suspension.strftime('%Y-%m-%d'),
            "delai_constate_mois": delai_mois,
            "delai_requis_mois": DELAI_DERNIER_EMPLOYEUR_MOIS,
            "article": "Art. D.161-2-12 et D.161-2-15 CSS"
        }
    
    return None

def generer_resume_suspension(suspension: Dict) -> str:
    """Génère un résumé pour cas de suspension"""
    
    return (f"SUSPENSION OBLIGATOIRE de la pension du {suspension['date_debut']} "
            f"au {suspension['date_fin']} (6 mois). "
            f"Raison : reprise chez le dernier employeur avant le délai réglementaire de 6 mois. "
            f"Cette suspension s'applique même si le plafond de cumul est respecté. "
            f"Aucune pension ne sera versée pendant cette période.")

def generer_resume_cer_plafonne(
    plafond: Decimal,
    total: Decimal,
    depassement: Decimal,
    ecretement: Optional[Dict]
) -> str:
    """Génère un résumé pour CER PLAFONNÉ"""
    
    if depassement == 0:
        return (f"Cumul Emploi Retraite PLAFONNÉ autorisé. "
                f"Total revenus + pensions : {float(total):.2f}€. "
                f"Plafond : {float(plafond):.2f}€. "
                f"Le plafond est respecté : cumul intégral autorisé sans réduction.")
    else:
        pensions_suspendues = [r for r, d in ecretement.items() if d['suspendu']]
        pensions_reduites = [r for r, d in ecretement.items() if not d['suspendu']]
        
        resume = (f"Cumul Emploi Retraite PLAFONNÉ avec ÉCRÊTEMENT. "
                  f"Total revenus + pensions : {float(total):.2f}€. "
                  f"Plafond : {float(plafond):.2f}€. "
                  f"Dépassement : {float(depassement):.2f}€. ")
        
        if pensions_suspendues:
            resume += f"Pension(s) suspendue(s) : {', '.join(pensions_suspendues)}. "
        if pensions_reduites:
            resume += f"Pension(s) réduite(s) : {', '.join(pensions_reduites)}."
        
        return resume

# ============================================================================
# CONTRÔLES DE COHÉRENCE
# ============================================================================

def executer_controles_coherence(params: Dict, type_cumul: str) -> List[Dict]:
    """Exécute tous les contrôles de cohérence"""
    
    controles_resultats = []
    
    date_naissance = datetime.fromisoformat(params['date_naissance'])
    date_effet = datetime.fromisoformat(params['date_effet_retraite'])
    age = calculer_age(date_naissance, date_effet)
    duree = params['duree_assurance_trimestres']
    duree_requise = obtenir_duree_taux_plein(date_naissance.year)
    retraites_non_liquidees = params.get('retraites_non_liquidees', [])
    
    # CER_C01: Âge légal
    if type_cumul == "CER_TOTAL" and age < AGE_LEGAL:
        controles_resultats.append({
            "id": "CER_C01",
            "statut": "ERREUR",
            "message": f"Âge {age:.1f} ans < âge légal {AGE_LEGAL} ans",
            "type": "ERREUR_CRITIQUE",
            "couleur": "ROUGE"
        })
    
    # CER_C02: Subsidiarité
    if type_cumul == "CER_TOTAL" and retraites_non_liquidees:
        controles_resultats.append({
            "id": "CER_C02",
            "statut": "ERREUR",
            "message": f"Subsidiarité non respectée : {', '.join(retraites_non_liquidees)}",
            "type": "ERREUR_CRITIQUE",
            "couleur": "ROUGE"
        })
    
    # CER_C03: Taux plein
    if age >= AGE_LEGAL and duree < duree_requise and age < AGE_TAUX_PLEIN_AUTO:
        trimestres_manquants = duree_requise - duree
        controles_resultats.append({
            "id": "CER_C03",
            "statut": "ALERTE",
            "message": (f"Taux plein non atteint : {duree}/{duree_requise} trimestres. "
                       f"Manque {trimestres_manquants} trimestres. CER PLAFONNÉ obligatoire."),
            "type": "ALERTE",
            "couleur": "ORANGE",
            "trimestres_manquants": trimestres_manquants
        })
    
    # CER_C04: Délai 6 mois (vérifié dans traiter_cer_plafonne)
    
    # CER_C05: Déclaration hors délai (nécessite date déclaration réelle)
    # À implémenter si date_declaration fournie
    
    # CER_C06: Dépassement plafond (vérifié dans traiter_cer_plafonne)
    
    # CER_C07: Période référence
    periode_ref = params.get('periode_reference', {})
    if not periode_ref.get('salaires_bruts'):
        controles_resultats.append({
            "id": "CER_C07",
            "statut": "ALERTE",
            "message": "Période de référence manquante ou incomplète",
            "type": "ALERTE",
            "couleur": "ORANGE"
        })
    
    # CER_C08: Activités multiples (à implémenter si données disponibles)
    
    return controles_resultats

# ============================================================================
# GÉNÉRATION ALERTES
# ============================================================================

def generer_alertes(
    controles: List[Dict],
    params: Dict,
    type_cumul: str
) -> List[Dict]:
    """Génère les alertes en fonction des contrôles"""
    
    alertes_generees = []
    
    for controle in controles:
        controle_id = controle['id']
        statut = controle['statut']
        
        # Mapper contrôles vers alertes
        if controle_id == "CER_C01" and statut == "ERREUR":
            alertes_generees.append({
                "code": "CER_A01",
                "niveau": "ROUGE",
                "message": "Âge inférieur à l'âge légal : CER TOTAL impossible",
                "action": "Bloquer CER TOTAL / Proposer CER PLAFONNÉ"
            })
        
        elif controle_id == "CER_C02" and statut == "ERREUR":
            alertes_generees.append({
                "code": "CER_A02",
                "niveau": "ROUGE",
                "message": "Subsidiarité non respectée : retraites obligatoires non liquidées",
                "action": "Lister régimes manquants / Bloquer CER TOTAL"
            })
        
        elif controle_id == "CER_C03" and statut == "ALERTE":
            alertes_generees.append({
                "code": "CER_A05",
                "niveau": "ORANGE",
                "message": "Taux plein non atteint : CER PLAFONNÉ obligatoire",
                "action": "Appliquer plafonds de cumul",
                "trimestres_manquants": controle.get('trimestres_manquants')
            })
        
        elif controle_id == "CER_C07" and statut == "ALERTE":
            alertes_generees.append({
                "code": "CER_A07",
                "niveau": "ORANGE",
                "message": "Période référence incohérente : justificatifs requis",
                "action": "Demander bulletins de salaire période de référence"
            })
    
    # Alerte proche 67 ans
    date_naissance = datetime.fromisoformat(params['date_naissance'])
    date_effet = datetime.fromisoformat(params['date_effet_retraite'])
    age = calculer_age(date_naissance, date_effet)
    
    if type_cumul == "CER_PLAFONNE" and age >= 66.5:
        alertes_generees.append({
            "code": "CER_A15",
            "niveau": "JAUNE",
            "message": "Assuré proche de 67 ans : basculement automatique vers CER TOTAL à prévoir",
            "action": "Préparer transition / Informer assuré"
        })
    
    return alertes_generees

# ============================================================================
# UTILITAIRES
# ============================================================================

def estimer_tokens(resultat: Dict) -> int:
    """Estime le nombre de tokens pour le résultat"""
    
    # Estimation grossière : ~4 caractères par token
    texte_json = json.dumps(resultat, ensure_ascii=False)
    nb_caracteres = len(texte_json)
    tokens = nb_caracteres // 4
    
    # Ajouter tokens pour le prompt et le contexte
    tokens_total = tokens + 500
    
    return tokens_total

def formater_montant(montant: float) -> str:
    """Formate un montant en euros"""
    return f"{montant:,.2f}€".replace(',', ' ')

def generer_rapport_textuel(resultat: Dict) -> str:
    """Génère un rapport textuel détaillé du résultat"""
    
    rapport = f"""
╔══════════════════════════════════════════════════════════════╗
║           CUMUL EMPLOI RETRAITE - ANALYSE DÉTAILLÉE          ║
╚══════════════════════════════════════════════════════════════╝

TYPE DE CUMUL : {resultat['type_cumul']}
AUTORISÉ : {'OUI' if resultat['autorise'] else 'NON'}

"""
    
    if resultat['type_cumul'] == "CER_PLAFONNE":
        rapport += f"""
─────────────────────────────────────────────────────────────
PLAFOND DE CUMUL
─────────────────────────────────────────────────────────────
Plafond calculé : {formater_montant(resultat.get('plafond', 0))}
Revenus activité : {formater_montant(resultat.get('revenus_activite', 0))}
Total pensions : {formater_montant(resultat.get('total_pensions', 0))}
TOTAL revenus + pensions : {formater_montant(resultat.get('total_revenus_pensions', 0))}
Dépassement : {formater_montant(resultat.get('depassement', 0))}

"""
        
        if resultat.get('ecretement'):
            rapport += """
─────────────────────────────────────────────────────────────
ÉCRÊTEMENT APPLIQUÉ
─────────────────────────────────────────────────────────────
"""
            for regime, detail in resultat['ecretement'].items():
                statut = "[SUSPENDU]" if detail['suspendu'] else ""
                rapport += f"""
{regime} :
  Montant initial : {formater_montant(detail['montant_initial'])}
  Réduction : {formater_montant(detail['reduction'])}
  Montant versé : {formater_montant(detail['montant_verse'])} {statut}
"""
    
    if resultat.get('suspension'):
        suspension = resultat['suspension']
        rapport += f"""
─────────────────────────────────────────────────────────────
⚠️  SUSPENSION OBLIGATOIRE
─────────────────────────────────────────────────────────────
Période : du {suspension['date_debut']} au {suspension['date_fin']}
Motif : {suspension['motif']}
Durée : {suspension['delai_requis_mois']} mois
Article : {suspension['article']}

"""
    
    if resultat.get('controles'):
        rapport += """
─────────────────────────────────────────────────────────────
CONTRÔLES DE COHÉRENCE
─────────────────────────────────────────────────────────────
"""
        for ctrl in resultat['controles']:
            icone = "❌" if ctrl['statut'] == "ERREUR" else "⚠️" if ctrl['statut'] == "ALERTE" else "✓"
            rapport += f"{icone} [{ctrl['statut']}] {ctrl['id']} : {ctrl['message']}\n"
    
    if resultat.get('alertes'):
        rapport += """
─────────────────────────────────────────────────────────────
ALERTES
─────────────────────────────────────────────────────────────
"""
        for alerte in resultat['alertes']:
            if alerte['niveau'] == "ROUGE":
                icone = "🔴"
            elif alerte['niveau'] == "ORANGE":
                icone = "🟠"
            else:
                icone = "🟡"
            rapport += f"{icone} [{alerte['niveau']}] {alerte['code']} : {alerte['message']}\n"
    
    rapport += f"""
─────────────────────────────────────────────────────────────
RÉSUMÉ
─────────────────────────────────────────────────────────────
{resultat.get('resume', '')}

Tokens estimés : {resultat.get('tokens_estimes', 0)}
"""
    
    return rapport

# ============================================================================
# FONCTIONS COMPLÉMENTAIRES POUR N8N
# ============================================================================

def calculer_plafond_simple(salaires: List[float]) -> float:
    """
    Version simplifiée pour calcul plafond seul
    Utilisable directement depuis N8N
    """
    plafond = calculer_plafond(salaires)
    return float(plafond)

def verifier_depassement_simple(
    revenus_activite: float,
    pensions: Dict[str, float],
    plafond: float
) -> Dict:
    """
    Version simplifiée pour vérification dépassement
    Utilisable directement depuis N8N
    """
    total_pensions = sum(pensions.values())
    total = revenus_activite + total_pensions
    depassement = max(0, total - plafond)
    
    return {
        "total_revenus_pensions": total,
        "plafond": plafond,
        "depassement": depassement,
        "depasse": depassement > 0
    }

# ============================================================================
# EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    
    print("="*80)
    print("EXEMPLES D'UTILISATION - CUMUL EMPLOI RETRAITE")
    print("="*80)
    
    # Exemple 1: CER TOTAL OK
    print("\n" + "="*80)
    print("EXEMPLE 1 : CER TOTAL - Conditions remplies")
    print("="*80)
    
    params1 = {
        "date_naissance": "1955-08-15",
        "date_effet_retraite": "2017-09-01",
        "duree_assurance_trimestres": 167,
        "retraites_liquidees": ["RG", "ARRCO"],
        "retraites_non_liquidees": []
    }
    
    resultat1 = api_handler(params1)
    print(generer_rapport_textuel(resultat1))
    
    # Exemple 2: CER PLAFONNÉ sans dépassement
    print("\n" + "="*80)
    print("EXEMPLE 2 : CER PLAFONNÉ - Respect du plafond")
    print("="*80)
    
    params2 = {
        "date_naissance": "1962-03-20",
        "date_effet_retraite": "2024-07-01",
        "duree_assurance_trimestres": 160,
        "retraites_liquidees": ["RG", "ARRCO"],
        "retraites_non_liquidees": [],
        "reprise_activite": {
            "date": "2025-01-15",
            "employeur": "Nouvelle_Entreprise",
            "dernier_employeur": False,
            "revenus_mensuels_bruts": 500
        },
        "periode_reference": {
            "salaires_bruts": [2100, 2100, 2100]
        },
        "pensions_mensuelles": {
            "RG": 900,
            "SNCF": 200,
            "complementaires": 600
        }
    }
    
    resultat2 = api_handler(params2)
    print(generer_rapport_textuel(resultat2))
    
    # Exemple 3: CER PLAFONNÉ avec écrêtement
    print("\n" + "="*80)
    print("EXEMPLE 3 : CER PLAFONNÉ - Écrêtement nécessaire")
    print("="*80)
    
    params3 = {
        "date_naissance": "1962-03-20",
        "date_effet_retraite": "2024-07-01",
        "duree_assurance_trimestres": 160,
        "retraites_liquidees": ["RG", "ARRCO"],
        "retraites_non_liquidees": [],
        "reprise_activite": {
            "date": "2025-01-15",
            "employeur": "Nouvelle_Entreprise",
            "dernier_employeur": False,
            "revenus_mensuels_bruts": 1200
        },
        "periode_reference": {
            "salaires_bruts": [2150, 2150, 2150]
        },
        "pensions_mensuelles": {
            "RG": 900,
            "SNCF": 200,
            "complementaires": 600
        }
    }
    
    resultat3 = api_handler(params3)
    print(generer_rapport_textuel(resultat3))
    
    # Exemple 4: Suspension délai 6 mois
    print("\n" + "="*80)
    print("EXEMPLE 4 : CER PLAFONNÉ - Suspension délai 6 mois")
    print("="*80)
    
    params4 = {
        "date_naissance": "1962-03-20",
        "date_effet_retraite": "2024-07-01",
        "duree_assurance_trimestres": 160,
        "retraites_liquidees": ["RG", "ARRCO"],
        "retraites_non_liquidees": [],
        "reprise_activite": {
            "date": "2024-07-01",
            "employeur": "Employeur_A",
            "dernier_employeur": True,
            "date_fin_contrat_dernier_employeur": "2024-04-30",
            "revenus_mensuels_bruts": 1500
        },
        "periode_reference": {
            "salaires_bruts": [2000, 2000, 2000]
        },
        "pensions_mensuelles": {
            "RG": 1000,
            "complementaires": 500
        }
    }
    
    resultat4 = api_handler(params4)
    print(generer_rapport_textuel(resultat4))
    
    print("\n" + "="*80)
    print("FIN DES EXEMPLES")
    print("="*80)
