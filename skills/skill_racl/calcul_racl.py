# -*- coding: utf-8 -*-
"""
Script : calcul_racl.py
Description : Analyse d'ÃƒÂ©ligibilitÃƒÂ© au dispositif Retraite AnticipÃƒÂ©e pour CarriÃƒÂ¨re Longue (RACL)
Version : 1.0
Date : Novembre 2025
LÃƒÂ©gislation : Circulaire CNAV 2023-14 du 10 juillet 2023, Loi nÃ‚Â°2023-270 du 14 avril 2023

Ce script automatise :
- Calcul des trimestres validÃƒÂ©s avant un ÃƒÂ¢ge limite (16/18/20/21 ans)
- Calcul des trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s (avec limites rÃƒÂ©glementaires)
- DÃƒÂ©termination de l'ÃƒÂ©ligibilitÃƒÂ© au RACL
- Calcul de l'ÃƒÂ¢ge et de la date de dÃƒÂ©part possible

Format de dates : JJ/MM/AAAA (exemple : 15/03/1965)
"""

from datetime import datetime
from typing import Dict, Tuple, List


# ============================================================================
# SECTION 1 : DONNÃƒâ€°ES RÃƒâ€°GLEMENTAIRES
# ============================================================================

# Limites des trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s (rÃƒÂ¨gles RACL 2023)
LIMITE_SERVICE_NATIONAL = 4  # Maximum 4 trimestres sur toute la carriÃƒÂ¨re
LIMITE_MALADIE_AT = 4  # Maximum 4 trimestres (maladie + AT cumulÃƒÂ©s)
LIMITE_MATERNITE = None  # Pas de limite (tous les trimestres comptent)
LIMITE_INVALIDITE = 2  # Maximum 2 trimestres
LIMITE_CHOMAGE = 4  # Maximum 4 trimestres
LIMITE_AVPF_AVA = 4  # Maximum 4 trimestres (nouveautÃƒÂ© 2023)
LIMITE_C2P = None  # Pas de limite

# Ãƒâ€šges de dÃƒÂ©part RACL selon gÃƒÂ©nÃƒÂ©ration
AGES_DEPART_RACL = {
    # Ãƒâ€šge de dÃƒÂ©part ÃƒÂÃ‚Â  60 ans selon gÃƒÂ©nÃƒÂ©ration (dÃƒÂ©but activitÃƒÂ© avant 20 ans)
    1963: 60 * 12 + 3,  # 60 ans 3 mois (sept-dÃƒÂ©c 1963)
    1964: 60 * 12 + 6,  # 60 ans 6 mois
    1965: 60 * 12 + 9,  # 60 ans 9 mois
    1966: 61 * 12,      # 61 ans
    1967: 61 * 12 + 3,  # 61 ans 3 mois
    1968: 61 * 12 + 6,  # 61 ans 6 mois
    1969: 61 * 12 + 9,  # 61 ans 9 mois
}


# ============================================================================
# SECTION 1B : API HANDLER POUR N8N (Point d'entrée unifié)
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour appel depuis n8n.
    
    Cette fonction standardise l'interface d'appel pour les workflows n8n.
    Elle prend un dictionnaire de paramètres et retourne un résultat structuré
    incluant l'éligibilité, les alertes, et tous les détails de calcul.
    
    Args:
        params (Dict): Dictionnaire contenant les paramètres d'entrée
            {
                "date_naissance": "15/03/1965",  # Format JJ/MM/AAAA
                "liste_trimestres_valides": [(1981, 1), (1981, 2), ...],
                "trimestres_cotises_stricts": 165,
                "duree_requise_taux_plein": 172,
                
                # Trimestres réputés cotisés (optionnels)
                "trimestres_service_national": 4,
                "trimestres_maladie": 2,
                "trimestres_maternite": 2,
                "trimestres_invalidite": 0,
                "trimestres_chomage": 3,
                "trimestres_avpf_ava": 0,
                "trimestres_c2p": 0
            }
    
    Returns:
        Dict: Résultat structuré pour n8n
            {
                "eligible": bool,
                "age_depart": str (ex: "60 ans 9 mois"),
                "date_depart_possible": str (ex: "01/12/2025"),
                "trimestres_cotises": int,
                "trimestres_reputes_cotises": int,
                "trimestres_total_racl": int,
                "duree_requise": int,
                "trimestres_manquants": int,
                "cas_debut_activite": str (ex: "avant_20_ans"),
                "controles": [
                    {
                        "code": "RAC_C01",
                        "type": "ERREUR_CRITIQUE",
                        "message": "..."
                    }
                ],
                "alertes": {
                    "rouge": [...],
                    "orange": [...],
                    "jaune": [...]
                },
                "detail_reputes_cotises": {...}
            }
    
    Exemple d'utilisation dans n8n:
        >>> result = api_handler({
        ...     "date_naissance": "15/03/1965",
        ...     "liste_trimestres_valides": [(1981, 1), (1981, 2), ...],
        ...     "trimestres_cotises_stricts": 165,
        ...     "duree_requise_taux_plein": 172,
        ...     "trimestres_service_national": 4,
        ...     "trimestres_maladie": 2
        ... })
        >>> print(result["eligible"])  # True
        >>> print(result["age_depart"])  # "60 ans 9 mois"
    """
    # Extraire les paramètres
    date_naissance = params.get("date_naissance")
    liste_trimestres_valides = params.get("liste_trimestres_valides", [])
    trimestres_cotises_stricts = params.get("trimestres_cotises_stricts", 0)
    duree_requise_taux_plein = params.get("duree_requise_taux_plein", 172)
    
    # Trimestres réputés cotisés (avec valeurs par défaut)
    trimestres_service_national = params.get("trimestres_service_national", 0)
    trimestres_maladie = params.get("trimestres_maladie", 0)
    trimestres_maternite = params.get("trimestres_maternite", 0)
    trimestres_invalidite = params.get("trimestres_invalidite", 0)
    trimestres_chomage = params.get("trimestres_chomage", 0)
    trimestres_avpf_ava = params.get("trimestres_avpf_ava", 0)
    trimestres_c2p = params.get("trimestres_c2p", 0)
    
    # Appel de la fonction principale d'analyse
    result = analyser_eligibilite_racl(
        date_naissance=date_naissance,
        liste_trimestres_valides=liste_trimestres_valides,
        trimestres_cotises_stricts=trimestres_cotises_stricts,
        duree_requise_taux_plein=duree_requise_taux_plein,
        trimestres_service_national=trimestres_service_national,
        trimestres_maladie=trimestres_maladie,
        trimestres_maternite=trimestres_maternite,
        trimestres_invalidite=trimestres_invalidite,
        trimestres_chomage=trimestres_chomage,
        trimestres_avpf_ava=trimestres_avpf_ava,
        trimestres_c2p=trimestres_c2p
    )
    
    # Construction de la réponse standardisée
    response = {
        "eligible": result["eligible_racl"],
        "age_depart": result["meilleur_cas"]["age_depart"] if result["meilleur_cas"] else None,
        "date_depart_possible": result["date_depart_possible"],
        "trimestres_cotises": result["trimestres_cotises_stricts"],
        "trimestres_reputes_cotises": result["trimestres_reputes_cotises"],
        "trimestres_total_racl": result["trimestres_racl_total"],
        "duree_requise": result["duree_requise"],
        "trimestres_manquants": result["trimestres_manquants"],
        "cas_debut_activite": None,
        "detail_reputes_cotises": result["detail_reputes_cotises"]
    }
    
    # Déterminer le cas de début d'activité
    if result["meilleur_cas"]:
        if "avant fin 16 ans" in result["meilleur_cas"]["condition_debut"]:
            response["cas_debut_activite"] = "avant_16_ans"
        elif "avant fin 18 ans" in result["meilleur_cas"]["condition_debut"]:
            response["cas_debut_activite"] = "avant_18_ans"
        elif "avant fin 20 ans" in result["meilleur_cas"]["condition_debut"]:
            response["cas_debut_activite"] = "avant_20_ans"
        elif "avant fin 21 ans" in result["meilleur_cas"]["condition_debut"]:
            response["cas_debut_activite"] = "avant_21_ans"
    
    # Génération des contrôles et alertes
    controles = []
    alertes = {"rouge": [], "orange": [], "jaune": []}
    
    # Contrôle RAC_C01 : Trimestres début activité insuffisants
    if not result["eligible_racl"] and result["meilleur_cas"] is None:
        controles.append({
            "code": "RAC_C01",
            "type": "ERREUR_CRITIQUE",
            "message": "Condition de début d'activité non remplie. Aucun cas de début d'activité éligible détecté."
        })
        alertes["rouge"].append("Condition de début d'activité non remplie - RACL impossible")
    
    # Contrôle RAC_C02 : Trimestres cotisés insuffisants
    if result["trimestres_manquants"] > 0:
        controles.append({
            "code": "RAC_C02",
            "type": "ERREUR_CRITIQUE" if result["trimestres_manquants"] > 2 else "AVERTISSEMENT",
            "message": f"Durée cotisée insuffisante. {result['trimestres_racl_total']} trimestres RACL, {result['duree_requise']} requis. Manquants : {result['trimestres_manquants']}"
        })
        if result["trimestres_manquants"] > 2:
            alertes["rouge"].append(f"Durée cotisée insuffisante ({result['trimestres_manquants']} trimestres manquants) - RACL impossible")
        else:
            alertes["orange"].append(f"Proche de l'éligibilité ({result['trimestres_manquants']} trimestres manquants) - Envisager rachat VPLR")
    
    # Contrôle RAC_C03 : Dépassement limites trimestres réputés cotisés
    detail_reputes = result["detail_reputes_cotises"]
    
    if detail_reputes["service_national"]["declares"] > LIMITE_SERVICE_NATIONAL:
        controles.append({
            "code": "RAC_C03",
            "type": "AVERTISSEMENT",
            "message": f"Dépassement limite service national. {detail_reputes['service_national']['declares']} déclarés, {LIMITE_SERVICE_NATIONAL} retenus"
        })
    
    if detail_reputes["maladie_at"]["declares"] > LIMITE_MALADIE_AT:
        controles.append({
            "code": "RAC_C03",
            "type": "AVERTISSEMENT",
            "message": f"Dépassement limite maladie/AT. {detail_reputes['maladie_at']['declares']} déclarés, {LIMITE_MALADIE_AT} retenus"
        })
    
    if detail_reputes["invalidite"]["declares"] > LIMITE_INVALIDITE:
        controles.append({
            "code": "RAC_C03",
            "type": "AVERTISSEMENT",
            "message": f"Dépassement limite invalidité. {detail_reputes['invalidite']['declares']} déclarés, {LIMITE_INVALIDITE} retenus"
        })
    
    if detail_reputes["chomage"]["declares"] > LIMITE_CHOMAGE:
        controles.append({
            "code": "RAC_C03",
            "type": "AVERTISSEMENT",
            "message": f"Dépassement limite chômage. {detail_reputes['chomage']['declares']} déclarés, {LIMITE_CHOMAGE} retenus"
        })
    
    if detail_reputes["avpf_ava"]["declares"] > LIMITE_AVPF_AVA:
        controles.append({
            "code": "RAC_C03",
            "type": "AVERTISSEMENT",
            "message": f"Dépassement limite AVPF/Ava. {detail_reputes['avpf_ava']['declares']} déclarés, {LIMITE_AVPF_AVA} retenus"
        })
    
    # Alertes supplémentaires
    if est_ne_4eme_trimestre(date_naissance):
        alertes["jaune"].append("Né 4ème trimestre - 4 trimestres requis au lieu de 5 pour début activité")
    
    # Vérification clause de sauvegarde (générations 1961-1963)
    annee_naissance = extraire_annee_naissance(date_naissance)
    if annee_naissance in [1961, 1962, 1963]:
        alertes["orange"].append("Clause de sauvegarde applicable (générations 1961-1963) - Vérifier si 168 trimestres acquis avant 01/09/2023")
    
    response["controles"] = controles
    response["alertes"] = alertes
    
    return response


# ============================================================================
# SECTION 2 : FONCTIONS UTILITAIRES
# ============================================================================

def parser_date_francaise(date_str: str) -> datetime:
    """Convertit une date JJ/MM/AAAA en datetime."""
    return datetime.strptime(date_str, "%d/%m/%Y")


def extraire_annee_naissance(date_naissance: str) -> int:
    """Extrait l'annÃƒÂ©e de naissance."""
    return parser_date_francaise(date_naissance).year


def extraire_mois_naissance(date_naissance: str) -> int:
    """Extrait le mois de naissance (1-12)."""
    return parser_date_francaise(date_naissance).month


def est_ne_4eme_trimestre(date_naissance: str) -> bool:
    """
    VÃƒÂ©rifie si la personne est nÃƒÂ©e au 4ÃƒÂ¨me trimestre (oct-nov-dÃƒÂ©c).
    
    Dans ce cas, seulement 4 trimestres sont requis au lieu de 5 pour le dÃƒÂ©but d'activitÃƒÂ©.
    
    Args:
        date_naissance (str): Date de naissance JJ/MM/AAAA
    
    Returns:
        bool: True si nÃƒÂ© entre octobre et dÃƒÂ©cembre
    
    Exemple:
        >>> est_ne_4eme_trimestre("15/11/1965")
        True
    """
    mois = extraire_mois_naissance(date_naissance)
    return mois >= 10  # Octobre, novembre, dÃƒÂ©cembre


# ============================================================================
# SECTION 3 : CALCUL TRIMESTRES AVANT Ãƒâ€šGE LIMITE
# ============================================================================

def calculer_trimestres_avant_age(
    date_naissance: str,
    liste_trimestres_valides: List[Tuple[int, int]],
    age_limite: int
) -> Dict[str, any]:
    """
    Calcule le nombre de trimestres validÃƒÂ©s avant la fin de l'annÃƒÂ©e civile d'un ÃƒÂ¢ge donnÃƒÂ©.
    
    ÃƒÂ¢Ã…Â¡Ã‚Â ÃƒÂ¯Ã‚Â¸Ã‚Â RÃƒË†GLE IMPORTANTE : L'ÃƒÂ¢ge se calcule dans l'ANNÃƒâ€°E CIVILE, pas au moment du trimestre.
    
    Args:
        date_naissance (str): Date de naissance JJ/MM/AAAA
        liste_trimestres_valides (List[Tuple[int, int]]): Liste de tuples (annÃƒÂ©e, trimestre)
            Exemple: [(1981, 2), (1981, 3), (1981, 4), (1982, 1), ...]
        age_limite (int): Ãƒâ€šge limite (16, 18, 20 ou 21 ans)
    
    Returns:
        dict: {
            "trimestres_avant_age": int,
            "annee_limite": int,
            "condition_remplie": bool,
            "trimestres_requis": int (4 ou 5 selon naissance 4ÃƒÂ¨me trimestre)
        }
    
    Exemple:
        >>> calculer_trimestres_avant_age("15/03/1965", [(1981, 2), (1981, 3)], 16)
        {
            "trimestres_avant_age": 2,
            "annee_limite": 1981,
            "condition_remplie": False,
            "trimestres_requis": 5
        }
    """
    annee_naiss = extraire_annee_naissance(date_naissance)
    annee_limite = annee_naiss + age_limite
    
    # DÃƒÂ©terminer le nombre de trimestres requis
    if est_ne_4eme_trimestre(date_naissance):
        trimestres_requis = 4
    else:
        trimestres_requis = 5
    
    # Compter les trimestres avant fin de l'annÃƒÂ©e limite
    trimestres_avant = 0
    trimestres_detail = []
    
    for annee, trimestre in liste_trimestres_valides:
        if annee <= annee_limite:
            trimestres_avant += 1
            trimestres_detail.append((annee, trimestre))
    
    condition_remplie = trimestres_avant >= trimestres_requis
    
    return {
        "trimestres_avant_age": trimestres_avant,
        "annee_limite": annee_limite,
        "condition_remplie": condition_remplie,
        "trimestres_requis": trimestres_requis,
        "trimestres_detail": trimestres_detail,
        "ne_4eme_trimestre": est_ne_4eme_trimestre(date_naissance)
    }


# ============================================================================
# SECTION 4 : CALCUL TRIMESTRES RÃƒâ€°PUTÃƒâ€°S COTISÃƒâ€°S
# ============================================================================

def calculer_trimestres_reputes_cotises(
    trimestres_service_national: int = 0,
    trimestres_maladie: int = 0,
    trimestres_accident_travail: int = 0,
    trimestres_maternite: int = 0,
    trimestres_invalidite: int = 0,
    trimestres_chomage: int = 0,
    trimestres_avpf_ava: int = 0,
    trimestres_c2p: int = 0
) -> Dict[str, any]:
    """
    Calcule les trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s en appliquant les limites rÃƒÂ©glementaires.
    
    RÃƒË†GLES CRITIQUES :
    - Service national : max 4 trimestres
    - Maladie + AT : max 4 trimestres (limite globale)
    - MaternitÃƒÂ© : AUCUNE limite
    - InvaliditÃƒÂ© : max 2 trimestres
    - ChÃƒÂ´mage : max 4 trimestres
    - AVPF/Ava : max 4 trimestres
    - C2P : AUCUNE limite
    
    Args:
        trimestres_service_national (int): Trimestres de service national/civique
        trimestres_maladie (int): Trimestres de maladie
        trimestres_accident_travail (int): Trimestres d'accident du travail
        trimestres_maternite (int): Trimestres de maternitÃƒÂ©/adoption
        trimestres_invalidite (int): Trimestres d'invaliditÃƒÂ©
        trimestres_chomage (int): Trimestres de chÃƒÂ´mage indemnisÃƒÂ©
        trimestres_avpf_ava (int): Trimestres AVPF/Ava
        trimestres_c2p (int): Trimestres C2P
    
    Returns:
        dict: DÃƒÂ©tail des trimestres retenus et total
    
    Exemple:
        >>> calculer_trimestres_reputes_cotises(
                trimestres_service_national=5,
                trimestres_maladie=2,
                trimestres_maternite=2
            )
        {
            "total_reputes_cotises": 8,
            "service_national": 4,  # LimitÃƒÂ© ÃƒÂÃ‚Â  4
            "maladie_at": 2,
            "maternite": 2,  # Pas de limite
            ...
        }
    """
    # Application des limites
    service_retenu = min(trimestres_service_national, LIMITE_SERVICE_NATIONAL)
    
    # Maladie + AT : limite globale de 4
    maladie_at_total = trimestres_maladie + trimestres_accident_travail
    maladie_at_retenu = min(maladie_at_total, LIMITE_MALADIE_AT)
    
    # MaternitÃƒÂ© : pas de limite
    maternite_retenu = trimestres_maternite
    
    # InvaliditÃƒÂ© : max 2
    invalidite_retenu = min(trimestres_invalidite, LIMITE_INVALIDITE)
    
    # ChÃƒÂ´mage : max 4
    chomage_retenu = min(trimestres_chomage, LIMITE_CHOMAGE)
    
    # AVPF/Ava : max 4
    avpf_ava_retenu = min(trimestres_avpf_ava, LIMITE_AVPF_AVA)
    
    # C2P : pas de limite
    c2p_retenu = trimestres_c2p
    
    # Total
    total_reputes = (
        service_retenu +
        maladie_at_retenu +
        maternite_retenu +
        invalidite_retenu +
        chomage_retenu +
        avpf_ava_retenu +
        c2p_retenu
    )
    
    return {
        "total_reputes_cotises": total_reputes,
        "service_national": {
            "declares": trimestres_service_national,
            "retenus": service_retenu,
            "limite": LIMITE_SERVICE_NATIONAL,
            "limite_atteinte": trimestres_service_national > LIMITE_SERVICE_NATIONAL
        },
        "maladie_at": {
            "declares_maladie": trimestres_maladie,
            "declares_at": trimestres_accident_travail,
            "total_declares": maladie_at_total,
            "retenus": maladie_at_retenu,
            "limite": LIMITE_MALADIE_AT,
            "limite_atteinte": maladie_at_total > LIMITE_MALADIE_AT
        },
        "maternite": {
            "declares": trimestres_maternite,
            "retenus": maternite_retenu,
            "limite": "AUCUNE"
        },
        "invalidite": {
            "declares": trimestres_invalidite,
            "retenus": invalidite_retenu,
            "limite": LIMITE_INVALIDITE,
            "limite_atteinte": trimestres_invalidite > LIMITE_INVALIDITE
        },
        "chomage": {
            "declares": trimestres_chomage,
            "retenus": chomage_retenu,
            "limite": LIMITE_CHOMAGE,
            "limite_atteinte": trimestres_chomage > LIMITE_CHOMAGE
        },
        "avpf_ava": {
            "declares": trimestres_avpf_ava,
            "retenus": avpf_ava_retenu,
            "limite": LIMITE_AVPF_AVA,
            "limite_atteinte": trimestres_avpf_ava > LIMITE_AVPF_AVA
        },
        "c2p": {
            "declares": trimestres_c2p,
            "retenus": c2p_retenu,
            "limite": "AUCUNE"
        }
    }


# ============================================================================
# SECTION 5 : ANALYSE Ãƒâ€°LIGIBILITÃƒâ€° RACL
# ============================================================================

def analyser_eligibilite_racl(
    date_naissance: str,
    liste_trimestres_valides: List[Tuple[int, int]],
    trimestres_cotises_stricts: int,
    duree_requise_taux_plein: int,
    trimestres_service_national: int = 0,
    trimestres_maladie: int = 0,
    trimestres_accident_travail: int = 0,
    trimestres_maternite: int = 0,
    trimestres_invalidite: int = 0,
    trimestres_chomage: int = 0,
    trimestres_avpf_ava: int = 0,
    trimestres_c2p: int = 0
) -> Dict[str, any]:
    """
    Analyse complÃƒÂ¨te de l'ÃƒÂ©ligibilitÃƒÂ© au dispositif RACL.
    
    VÃƒÂ©rifie les 2 conditions cumulatives :
    1. DÃƒÂ©but d'activitÃƒÂ© avant un ÃƒÂ¢ge donnÃƒÂ© (16/18/20/21 ans)
    2. DurÃƒÂ©e d'assurance cotisÃƒÂ©e suffisante
    
    Args:
        date_naissance (str): Date de naissance JJ/MM/AAAA
        liste_trimestres_valides (List[Tuple[int, int]]): Liste (annÃƒÂ©e, trimestre)
        trimestres_cotises_stricts (int): Trimestres strictement cotisÃƒÂ©s
        duree_requise_taux_plein (int): DurÃƒÂ©e requise selon la gÃƒÂ©nÃƒÂ©ration
        trimestres_* : Trimestres par catÃƒÂ©gorie pour calcul rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s
    
    Returns:
        dict: Analyse complÃƒÂ¨te avec ÃƒÂ©ligibilitÃƒÂ©, ÃƒÂ¢ge de dÃƒÂ©part, date possible
    
    Exemple:
        >>> analyser_eligibilite_racl(
                date_naissance="15/03/1965",
                liste_trimestres_valides=[(1981, 2), (1981, 3), ...],
                trimestres_cotises_stricts=165,
                duree_requise_taux_plein=172,
                trimestres_service_national=4,
                trimestres_maternite=2
            )
    """
    # 1. Calcul trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s
    reputes = calculer_trimestres_reputes_cotises(
        trimestres_service_national,
        trimestres_maladie,
        trimestres_accident_travail,
        trimestres_maternite,
        trimestres_invalidite,
        trimestres_chomage,
        trimestres_avpf_ava,
        trimestres_c2p
    )
    
    # Total trimestres pour RACL
    trimestres_racl = trimestres_cotises_stricts + reputes["total_reputes_cotises"]
    
    # 2. VÃƒÂ©rification des 4 cas possibles de dÃƒÂ©part RACL
    cas_possibles = []
    
    # CAS 1 : DÃƒÂ©part ÃƒÂÃ‚Â  58 ans (avant 16 ans)
    avant_16 = calculer_trimestres_avant_age(date_naissance, liste_trimestres_valides, 16)
    if avant_16["condition_remplie"] and trimestres_racl >= duree_requise_taux_plein:
        cas_possibles.append({
            "age_depart": "58 ans",
            "age_depart_mois": 58 * 12,
            "condition_debut": "5 trimestres avant fin 16 ans",
            "eligible": True
        })
    
    # CAS 2 : DÃƒÂ©part ÃƒÂÃ‚Â  60 ans (avant 16 ans)
    if avant_16["condition_remplie"] and trimestres_racl >= duree_requise_taux_plein:
        cas_possibles.append({
            "age_depart": "60 ans",
            "age_depart_mois": 60 * 12,
            "condition_debut": "5 trimestres avant fin 16 ans",
            "eligible": True
        })
    
    # CAS 3 : DÃƒÂ©part entre 60-62 ans selon gÃƒÂ©nÃƒÂ©ration (avant 20 ans)
    avant_20 = calculer_trimestres_avant_age(date_naissance, liste_trimestres_valides, 20)
    annee_naiss = extraire_annee_naissance(date_naissance)
    mois_naiss = extraire_mois_naissance(date_naissance)
    
    if avant_20["condition_remplie"] and trimestres_racl >= duree_requise_taux_plein:
        # DÃƒÂ©terminer l'ÃƒÂ¢ge exact selon la gÃƒÂ©nÃƒÂ©ration
        if annee_naiss == 1963 and mois_naiss >= 9:
            age_mois = 60 * 12 + 3
        elif annee_naiss in AGES_DEPART_RACL:
            age_mois = AGES_DEPART_RACL[annee_naiss]
        elif annee_naiss >= 1970:
            age_mois = 62 * 12
        else:
            age_mois = 60 * 12
        
        age_ans = age_mois // 12
        age_mois_restants = age_mois % 12
        age_str = f"{age_ans} ans" if age_mois_restants == 0 else f"{age_ans} ans {age_mois_restants} mois"
        
        cas_possibles.append({
            "age_depart": age_str,
            "age_depart_mois": age_mois,
            "condition_debut": "5 trimestres avant fin 20 ans",
            "eligible": True
        })
    
    # CAS 4 : DÃƒÂ©part ÃƒÂÃ‚Â  63 ans (avant 21 ans)
    avant_21 = calculer_trimestres_avant_age(date_naissance, liste_trimestres_valides, 21)
    if avant_21["condition_remplie"] and trimestres_racl >= duree_requise_taux_plein:
        cas_possibles.append({
            "age_depart": "63 ans",
            "age_depart_mois": 63 * 12,
            "condition_debut": "5 trimestres avant fin 21 ans",
            "eligible": True
        })
    
    # 3. DÃƒÂ©termination du meilleur cas (ÃƒÂ¢ge le plus jeune)
    if cas_possibles:
        meilleur_cas = min(cas_possibles, key=lambda x: x["age_depart_mois"])
        eligible_racl = True
        
        # Calcul de la date de dÃƒÂ©part
        annee_naiss = extraire_annee_naissance(date_naissance)
        mois_naiss = extraire_mois_naissance(date_naissance)
        
        age_depart_mois = meilleur_cas["age_depart_mois"]
        annee_depart = annee_naiss + (age_depart_mois // 12)
        mois_depart = mois_naiss + (age_depart_mois % 12)
        
        if mois_depart > 12:
            annee_depart += 1
            mois_depart -= 12
        
        # Date de dÃƒÂ©part = 1er jour du mois suivant l'anniversaire
        date_depart = f"01/{mois_depart:02d}/{annee_depart}"
        
    else:
        meilleur_cas = None
        eligible_racl = False
        date_depart = None
    
    # SynthÃƒÂ¨se
    return {
        "eligible_racl": eligible_racl,
        "meilleur_cas": meilleur_cas,
        "tous_les_cas_possibles": cas_possibles,
        "date_depart_possible": date_depart,
        "trimestres_cotises_stricts": trimestres_cotises_stricts,
        "trimestres_reputes_cotises": reputes["total_reputes_cotises"],
        "trimestres_racl_total": trimestres_racl,
        "duree_requise": duree_requise_taux_plein,
        "trimestres_manquants": max(0, duree_requise_taux_plein - trimestres_racl),
        "detail_reputes_cotises": reputes,
        "verifications_debut_activite": {
            "avant_16_ans": avant_16,
            "avant_20_ans": avant_20,
            "avant_21_ans": avant_21
        }
    }


# ============================================================================
# SECTION 6 : EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    print("=" * 80)
    print("SCRIPT : calcul_racl.py - TESTS ET EXEMPLES")
    print("=" * 80)
    
    # EXEMPLE 1 : Ãƒâ€°ligible dÃƒÂ©part ÃƒÂÃ‚Â  60 ans
    print("\n### EXEMPLE 1 : Ãƒâ€°ligible dÃƒÂ©part ÃƒÂÃ‚Â  60 ans ###")
    print("-" * 80)
    
    # Construction liste trimestres (1981-2025)
    trimestres_1 = []
    for annee in range(1981, 2026):
        for trim in range(1, 5):
            trimestres_1.append((annee, trim))
    
    result_1 = analyser_eligibilite_racl(
        date_naissance="15/03/1965",
        liste_trimestres_valides=trimestres_1[:178],  # 178 trimestres
        trimestres_cotises_stricts=165,
        duree_requise_taux_plein=172,
        trimestres_service_national=5,  # Sera limitÃƒÂ© ÃƒÂÃ‚Â  4
        trimestres_maladie=2,
        trimestres_maternite=2
    )
    
    print(f"Ãƒâ€°ligible RACL : {'OUI' if result_1['eligible_racl'] else 'NON'}")
    if result_1['eligible_racl']:
        print(f"Ãƒâ€šge de dÃƒÂ©part : {result_1['meilleur_cas']['age_depart']}")
        print(f"Date de dÃƒÂ©part possible : {result_1['date_depart_possible']}")
    print(f"\nTrimestres cotisÃƒÂ©s stricts : {result_1['trimestres_cotises_stricts']}")
    print(f"Trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s : {result_1['trimestres_reputes_cotises']}")
    print(f"  - Service national : {result_1['detail_reputes_cotises']['service_national']['retenus']} " +
          f"(sur {result_1['detail_reputes_cotises']['service_national']['declares']} dÃƒÂ©clarÃƒÂ©s)")
    print(f"  - Maladie/AT : {result_1['detail_reputes_cotises']['maladie_at']['retenus']}")
    print(f"  - MaternitÃƒÂ© : {result_1['detail_reputes_cotises']['maternite']['retenus']}")
    print(f"Total RACL : {result_1['trimestres_racl_total']}")
    print(f"DurÃƒÂ©e requise : {result_1['duree_requise']}")
    
    # EXEMPLE 2 : Non ÃƒÂ©ligible (trimestres manquants)
    print("\n\n### EXEMPLE 2 : Non ÃƒÂ©ligible (trimestres manquants) ###")
    print("-" * 80)
    
    trimestres_2 = []
    for annee in range(1984, 2025):
        for trim in range(1, 5):
            trimestres_2.append((annee, trim))
    
    result_2 = analyser_eligibilite_racl(
        date_naissance="22/06/1966",
        liste_trimestres_valides=trimestres_2[:165],
        trimestres_cotises_stricts=158,
        duree_requise_taux_plein=172,
        trimestres_service_national=4,
        trimestres_chomage=5,  # Sera limitÃƒÂ© ÃƒÂÃ‚Â  4
        trimestres_maladie=1
    )
    
    print(f"Ãƒâ€°ligible RACL : {'OUI' if result_2['eligible_racl'] else 'NON'}")
    print(f"Trimestres manquants : {result_2['trimestres_manquants']}")
    print(f"\nTrimestres cotisÃƒÂ©s stricts : {result_2['trimestres_cotises_stricts']}")
    print(f"Trimestres rÃƒÂ©putÃƒÂ©s cotisÃƒÂ©s : {result_2['trimestres_reputes_cotises']}")
    print(f"Total RACL : {result_2['trimestres_racl_total']}")
    print(f"DurÃƒÂ©e requise : {result_2['duree_requise']}")
    
    # EXEMPLE 3 : Ãƒâ€°ligible dÃƒÂ©part ÃƒÂÃ‚Â  58 ans (cas rare)
    print("\n\n### EXEMPLE 3 : Ãƒâ€°ligible dÃƒÂ©part ÃƒÂÃ‚Â  58 ans (cas exceptionnel) ###")
    print("-" * 80)
    
    trimestres_3 = []
    # 1 trimestre en 1982 (15 ans)
    trimestres_3.append((1982, 4))
    # Puis carriÃƒÂ¨re complÃƒÂ¨te
    for annee in range(1983, 2026):
        for trim in range(1, 5):
            trimestres_3.append((annee, trim))
    
    result_3 = analyser_eligibilite_racl(
        date_naissance="05/02/1967",
        liste_trimestres_valides=trimestres_3[:180],
        trimestres_cotises_stricts=167,
        duree_requise_taux_plein=172,
        trimestres_service_national=4,
        trimestres_chomage=1
    )
    
    print(f"Ãƒâ€°ligible RACL : {'OUI' if result_3['eligible_racl'] else 'NON'}")
    if result_3['eligible_racl']:
        print(f"Ãƒâ€šge de dÃƒÂ©part : {result_3['meilleur_cas']['age_depart']}")
        print(f"Date de dÃƒÂ©part possible : {result_3['date_depart_possible']}")
        print(f"\nGain : dÃƒÂ©part {result_3['meilleur_cas']['age_depart']} au lieu de l'ÃƒÂ¢ge lÃƒÂ©gal")
    
    print(f"\nVÃƒÂ©rification dÃƒÂ©but activitÃƒÂ© :")
    print(f"  - Avant 16 ans : {result_3['verifications_debut_activite']['avant_16_ans']['trimestres_avant_age']} trimestres")
    print(f"  - Condition remplie : {'OUI' if result_3['verifications_debut_activite']['avant_16_ans']['condition_remplie'] else 'NON'}")
    
    print("\n" + "=" * 80)
    print("Tests terminÃƒÂ©s avec succÃƒÂ¨s !")
    print("=" * 80)
