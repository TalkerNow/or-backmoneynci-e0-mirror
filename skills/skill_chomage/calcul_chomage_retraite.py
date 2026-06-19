# -*- coding: utf-8 -*-
"""
Script : calcul_chomage_retraite.py
Description : Analyse des périodes de chômage et de leur impact sur la retraite française
Version : 1.0
Date : Avril 2026
Législation : à valider — non précisée dans le skill_md d'origine

Ce script automatise :
- Qualification d'une période de chômage (4 catégories)
- Conversion des périodes indemnisées en trimestres assimilés
- Analyse de l'impact retraite (SAM, carrière longue, complémentaire)
- Estimation des points complémentaires potentiels

Format de dates : JJ/MM/AAAA (exemple : 15/03/1965)

GENÈSE : structuré à partir de SKILL_chomage.md (rédigé par JF). Le fichier
calcul d'origine annoncé dans la livraison du 07/04/2026 n'a pas été fourni —
reconstruit en respectant le format des calculateurs existants
(cf. calcul_trimestres_etranger.py). AUCUN seuil n'est inventé : toutes les
constantes proviennent du skill_md. Les éléments non spécifiés renvoient une
alerte 'a_valider' plutôt qu'une valeur fabriquée.
"""

from datetime import datetime
from typing import Dict, Any, List


# ============================================================================
# SECTION 1 : CONSTANTES (issues de SKILL_chomage.md uniquement)
# ============================================================================

JOURS_PAR_TRIMESTRE_ASSIMILE = 50          # 1 trimestre assimilé pour 50 jours indemnisés
MAX_TRIMESTRES_PAR_AN = 4                   # plafond annuel chômage indemnisé
PLAFOND_AUTONOME_AVANT_2011 = 4            # première période non indemnisé autonome
PLAFOND_AUTONOME_DEPUIS_2011 = 6
ANNEE_BASCULE_AUTONOME = 2011
LIMITE_STANDARD_NON_INDEMNISE_ANS = 1     # non indemnisé après indemnisé
EXCEPTION_SENIOR_ANS = 5

CONTROLES_IDS = [
    "CHO_C01", "CHO_C02", "CHO_C03", "CHO_C04", "CHO_C05",
    "CHO_C06", "CHO_C07", "CHO_C08", "CHO_C09",
]

CATEGORIES = {
    "chomage_indemnise": "Chômage indemnisé",
    "chomage_non_indemnise_apres_indemnise": "Chômage non indemnisé suite à une période indemnisée",
    "premiere_periode_non_indemnise_autonome": "Première période de chômage non indemnisé autonome",
    "chomage_non_indemnise_non_qualifie": "Chômage non indemnisé non qualifié",
}


# ============================================================================
# SECTION 2 : OUTILS
# ============================================================================

def parser_date_francaise(date_str: str) -> datetime:
    """Parse une date au format JJ/MM/AAAA."""
    return datetime.strptime(date_str.strip(), "%d/%m/%Y")


# ============================================================================
# SECTION 3 : QUALIFICATION DE LA PÉRIODE
# ============================================================================

def qualifier_periode(params: Dict[str, Any]) -> Dict[str, Any]:
    """
    Classe une période dans l'une des 4 catégories du skill_md.

    Entrées (booléens fournis par le consultant / l'extraction) :
        indemnise                 : période indemnisée
        fait_suite_a_indemnise    : non indemnisée mais consécutive à une indemnisée
        premiere_periode_autonome : première période non indemnisée autonome
        qualifie                  : période non indemnisée par ailleurs qualifiée
    """
    if params.get("indemnise"):
        categorie = "chomage_indemnise"
    elif params.get("fait_suite_a_indemnise"):
        categorie = "chomage_non_indemnise_apres_indemnise"
    elif params.get("premiere_periode_autonome"):
        categorie = "premiere_periode_non_indemnise_autonome"
    else:
        categorie = "chomage_non_indemnise_non_qualifie"

    return {
        "categorie": categorie,
        "label": CATEGORIES[categorie],
        "controle_manuel_requis": categorie == "chomage_non_indemnise_non_qualifie",
    }


# ============================================================================
# SECTION 4 : CALCUL DES TRIMESTRES ASSIMILÉS
# ============================================================================

def calculer_trimestres(params: Dict[str, Any]) -> Dict[str, Any]:
    """
    Calcule les trimestres assimilés d'une période, selon sa catégorie.
    Aucune valeur n'est extrapolée hors des règles du skill_md.
    """
    categorie = params.get("categorie", "")
    alertes: List[str] = []

    if categorie == "chomage_indemnise":
        jours = int(params.get("jours_indemnises", 0))
        brut = jours // JOURS_PAR_TRIMESTRE_ASSIMILE
        trimestres = min(MAX_TRIMESTRES_PAR_AN, brut)
        plafond = MAX_TRIMESTRES_PAR_AN

    elif categorie == "premiere_periode_non_indemnise_autonome":
        annee = int(params.get("annee_debut", 0))
        if annee == 0:
            return {"error": "annee_debut requise pour la catégorie autonome", "controles_ids": CONTROLES_IDS}
        plafond = PLAFOND_AUTONOME_AVANT_2011 if annee < ANNEE_BASCULE_AUTONOME else PLAFOND_AUTONOME_DEPUIS_2011
        trimestres = min(plafond, int(params.get("trimestres_demandes", plafond)))

    elif categorie == "chomage_non_indemnise_apres_indemnise":
        # Limite standard 1 an ; exception senior jusqu'à 5 ans (conditions a_valider).
        senior = bool(params.get("exception_senior", False))
        annees_max = EXCEPTION_SENIOR_ANS if senior else LIMITE_STANDARD_NON_INDEMNISE_ANS
        plafond = annees_max * MAX_TRIMESTRES_PAR_AN
        trimestres = min(plafond, int(params.get("trimestres_demandes", plafond)))
        if senior:
            alertes.append("CHO_C05: exception senior 5 ans — conditions âge/carrière à valider (non spécifiées dans le skill_md)")

    else:  # chomage_non_indemnise_non_qualifie
        trimestres = 0
        plafond = 0
        alertes.append("CHO_C07: période non qualifiée — ne pas retenir automatiquement, contrôle manuel requis")

    return {
        "categorie": categorie,
        "trimestres_assimiles": trimestres,
        "plafond_applique": plafond,
        "alertes": alertes,
        "controles_ids": CONTROLES_IDS,
    }


# ============================================================================
# SECTION 5 : IMPACT RETRAITE
# ============================================================================

def analyser_impact(params: Dict[str, Any]) -> Dict[str, Any]:
    """Synthétise l'impact retraite d'une période (selon le skill_md)."""
    categorie = params.get("categorie", "")
    indemnise = categorie == "chomage_indemnise"
    return {
        "categorie": categorie,
        "impact_sam": "aucun" if indemnise else "non précisé (a_valider)",
        "points_complementaires": "potentiels" if indemnise else "aucun",
        "carriere_longue": "trimestres assimilés — vérifier impact RACL (CHO_C08, a_valider)",
        "liens_skills": ["skill_racl", "skill_complementaires"],
        "controles_ids": CONTROLES_IDS,
    }


# ============================================================================
# SECTION 6 : ESTIMATION POINTS COMPLÉMENTAIRES
# ============================================================================

def estimer_points(params: Dict[str, Any]) -> Dict[str, Any]:
    """
    Le skill_md indique des points complémentaires 'potentiels' sur les périodes
    indemnisées mais NE fournit aucune formule de calcul. On ne fabrique donc
    aucun nombre : on délègue au skill complémentaires.
    """
    indemnise = params.get("categorie") == "chomage_indemnise"
    return {
        "statut": "potentiels" if indemnise else "aucun",
        "estimation": None,
        "note": "Aucune formule dans le skill_md — estimation à réaliser via skill_complementaires (AGIRC-ARRCO).",
        "a_valider": True,
        "controles_ids": CONTROLES_IDS,
    }


# ============================================================================
# SECTION 7 : HANDLER API N8N
# ============================================================================

def api_handler(event: dict) -> dict:
    """
    Handler standardisé pour intégration N8N.

    event = {"action": str, "params": dict}
    Actions : qualifier_periode | calculer_trimestres | analyser_impact | estimer_points
    Retour  : {"success": bool, "data": dict, "error": str?, "metadata": dict}
    """
    try:
        action = event.get("action", "")
        params = event.get("params", {})

        if action == "qualifier_periode":
            data = qualifier_periode(params)
        elif action == "calculer_trimestres":
            data = calculer_trimestres(params)
        elif action == "analyser_impact":
            data = analyser_impact(params)
        elif action == "estimer_points":
            data = estimer_points(params)
        else:
            return {
                "success": False,
                "error": f"Action '{action}' non reconnue",
                "actions_disponibles": [
                    "qualifier_periode", "calculer_trimestres",
                    "analyser_impact", "estimer_points",
                ],
            }

        return {
            "success": True,
            "data": data,
            "metadata": {
                "action": action,
                "version_script": "1.0",
                "controles_ids": CONTROLES_IDS,
                "timestamp": datetime.now().isoformat(),
            },
        }

    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "metadata": {"action": event.get("action", "unknown"), "version_script": "1.0"},
        }
