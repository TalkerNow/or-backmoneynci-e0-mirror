#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Calcul Retraite Progressive (RP)
Module de calcul pour le dispositif d'aménagement de fin de carrière

Version: 1.0
Date: 2025-11-10
Projet: Calculateur Retraite - EOR

Fonctionnalités:
- Vérification éligibilité RP (3 conditions)
- Calcul fraction de pension (40-80% temps partiel)
- Gestion multi-employeurs
- Calcul pension provisoire et définitive
- Contrôles de cohérence

Base réglementaire:
- CSS L.351-15, L.351-16
- Décret 2017-1645 du 30/11/2017
- Circulaire CNAV 2018-31 du 21/12/2018
"""

from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional
from dateutil.relativedelta import relativedelta
import json


# ========== CONSTANTES ==========

TRIMESTRES_MINIMUM_RP = 150
QUOTITE_MIN = 40
QUOTITE_MAX = 80
DECOTE_MAX_RP = 25.0  # Plafond spécifique RP (au lieu de 37.5%)
DECOTE_PAR_TRIMESTRE = 0.625
TAUX_PLEIN = 50.0

# Durées conventionnelles
DUREE_CONV_PARTICULIER_EMPLOYEUR = 40  # heures/semaine
DUREE_CONV_ASSISTANTE_MATERNELLE = 45  # heures/semaine

# Âges légaux par génération (simplifiés - à compléter selon circulaire)
AGES_LEGAUX = {
    1962: (62, 6),   # 62 ans 6 mois
    1963: (62, 9),   # 62 ans 9 mois
    1964: (63, 0),
    1965: (63, 0),
    1966: (63, 0),
    1967: (63, 0),
    1968: (63, 3),
    1969: (63, 6),
    1970: (63, 9),
    1971: (63, 9),
    1972: (64, 0),
    1973: (64, 0),
}

# Durées d'assurance requises par génération
DUREES_REQUISES = {
    1962: 169,
    1963: 170,
    1964: 171,
    1965: 172,
    1966: 172,
    1967: 172,
    1968: 172,
    1969: 172,
    1970: 172,
    1971: 172,
    1972: 172,
    1973: 172,
}


# ========== FONCTIONS DE CALCUL ==========

def calculer_age_rp_minimal(date_naissance: str) -> Tuple[int, int, datetime]:
    """
    Calcule l'âge minimum pour accéder à la RP.
    
    Règle: Âge légal - 2 ans, sans pouvoir être inférieur à 60 ans
    
    Args:
        date_naissance: Format "DD/MM/YYYY" ou "YYYY-MM-DD"
    
    Returns:
        Tuple (années, mois, date_eligibilite)
    
    Exemple:
        >>> calculer_age_rp_minimal("15/03/1965")
        (61, 0, datetime(2026, 3, 15))
    """
    try:
        if "/" in date_naissance:
            dt_naissance = datetime.strptime(date_naissance, "%d/%m/%Y")
        else:
            dt_naissance = datetime.strptime(date_naissance, "%Y-%m-%d")
    except ValueError as e:
        raise ValueError(f"Format de date invalide: {date_naissance}. Utiliser DD/MM/YYYY ou YYYY-MM-DD") from e
    
    annee_naissance = dt_naissance.year
    
    # Récupérer l'âge légal pour la génération
    if annee_naissance < 1962:
        age_legal = (62, 0)
    elif annee_naissance > 1973:
        age_legal = (64, 0)
    else:
        age_legal = AGES_LEGAUX.get(annee_naissance, (64, 0))
    
    # Calculer âge RP: âge légal - 2 ans
    age_rp_ans = age_legal[0] - 2
    age_rp_mois = age_legal[1]
    
    # Minimum 60 ans
    if age_rp_ans < 60:
        age_rp_ans = 60
        age_rp_mois = 0
    
    # Calculer la date d'éligibilité
    date_eligibilite = dt_naissance + relativedelta(years=age_rp_ans, months=age_rp_mois)
    
    return age_rp_ans, age_rp_mois, date_eligibilite


def verifier_duree_assurance(trimestres_tous_regimes: int) -> Dict:
    """
    Vérifie si la durée d'assurance de 150 trimestres est atteinte.
    
    Args:
        trimestres_tous_regimes: Nombre de trimestres tous régimes confondus (+ PRE)
    
    Returns:
        Dict avec résultat et détails
    """
    eligible = trimestres_tous_regimes >= TRIMESTRES_MINIMUM_RP
    trimestres_manquants = max(0, TRIMESTRES_MINIMUM_RP - trimestres_tous_regimes)
    
    return {
        "eligible": eligible,
        "trimestres_valides": trimestres_tous_regimes,
        "trimestres_requis": TRIMESTRES_MINIMUM_RP,
        "trimestres_manquants": trimestres_manquants,
        "controle": "RP_C02",
        "message": f"Durée d'assurance {'suffisante' if eligible else 'insuffisante'}: {trimestres_tous_regimes} / {TRIMESTRES_MINIMUM_RP} trimestres"
    }


def calculer_quotite_travail(heures_temps_partiel: float, heures_temps_complet: float) -> float:
    """
    Calcule la quotité de travail à temps partiel.
    
    Args:
        heures_temps_partiel: Heures travaillées (hebdo, mensuel ou annuel)
        heures_temps_complet: Heures temps complet de référence (même unité)
    
    Returns:
        Quotité en pourcentage (arrondi à l'entier le plus proche)
    
    Exemple:
        >>> calculer_quotite_travail(24, 35)
        69
    """
    if heures_temps_complet == 0:
        raise ValueError("Heures temps complet ne peut pas être zéro")
    
    quotite = (heures_temps_partiel / heures_temps_complet) * 100
    return round(quotite)


def calculer_quotite_multi_employeurs(emplois: List[Dict]) -> Dict:
    """
    Calcule la quotité totale pour plusieurs employeurs.
    
    Args:
        emplois: Liste de dicts avec clés 'heures_tp', 'heures_tc', 'type_employeur'
    
    Returns:
        Dict avec quotité totale et détails par employeur
    
    Exemple:
        >>> emplois = [
        ...     {"heures_tp": 18, "heures_tc": 35, "type_employeur": "entreprise"},
        ...     {"heures_tp": 12, "heures_tc": 40, "type_employeur": "particulier"}
        ... ]
        >>> calculer_quotite_multi_employeurs(emplois)
        {'quotite_totale': 81, 'details': [...]}
    """
    quotites = []
    
    for i, emploi in enumerate(emplois, 1):
        heures_tp = emploi.get("heures_tp", 0)
        heures_tc = emploi.get("heures_tc", 0)
        
        # Appliquer durée conventionnelle selon type
        type_emp = emploi.get("type_employeur", "entreprise").lower()
        if type_emp == "particulier":
            heures_tc = heures_tc or DUREE_CONV_PARTICULIER_EMPLOYEUR
        elif type_emp == "assistante_maternelle":
            heures_tc = heures_tc or DUREE_CONV_ASSISTANTE_MATERNELLE
        
        quotite = (heures_tp / heures_tc) * 100 if heures_tc > 0 else 0
        
        quotites.append({
            "employeur": i,
            "type": type_emp,
            "heures_tp": heures_tp,
            "heures_tc": heures_tc,
            "quotite": round(quotite, 2)
        })
    
    quotite_totale = sum(q["quotite"] for q in quotites)
    
    return {
        "quotite_totale": round(quotite_totale),
        "nombre_employeurs": len(emplois),
        "details": quotites,
        "controle": "RP_C03"
    }


def verifier_quotite_eligibilite(quotite: int) -> Dict:
    """
    Vérifie si la quotité de travail est dans les limites (40-80%).
    
    Args:
        quotite: Quotité de travail en pourcentage
    
    Returns:
        Dict avec résultat et message
    """
    eligible = QUOTITE_MIN <= quotite <= QUOTITE_MAX
    
    if quotite < QUOTITE_MIN:
        message = f"Quotité insuffisante: {quotite}% (minimum {QUOTITE_MIN}%)"
    elif quotite > QUOTITE_MAX:
        message = f"Quotité excessive: {quotite}% (maximum {QUOTITE_MAX}%)"
    else:
        message = f"Quotité conforme: {quotite}% (entre {QUOTITE_MIN}% et {QUOTITE_MAX}%)"
    
    return {
        "eligible": eligible,
        "quotite": quotite,
        "quotite_min": QUOTITE_MIN,
        "quotite_max": QUOTITE_MAX,
        "controle": "RP_C03",
        "message": message
    }


def calculer_pension_entiere_provisoire(
    sam: float,
    trimestres_rg: int,
    trimestres_tous_regimes: int,
    date_naissance: str,
    majoration_enfants: float = 0.0
) -> Dict:
    """
    Calcule la pension entière provisoire pour la RP.
    
    Args:
        sam: Salaire annuel moyen (€)
        trimestres_rg: Trimestres validés au régime général
        trimestres_tous_regimes: Trimestres tous régimes (pour le taux)
        date_naissance: Date de naissance
        majoration_enfants: Majoration pour enfants en % (ex: 10 pour 10%)
    
    Returns:
        Dict avec pension entière et détails de calcul
    """
    # Récupérer la durée requise pour la génération
    try:
        if "/" in date_naissance:
            dt_naissance = datetime.strptime(date_naissance, "%d/%m/%Y")
        else:
            dt_naissance = datetime.strptime(date_naissance, "%Y-%m-%d")
    except ValueError:
        raise ValueError(f"Format de date invalide: {date_naissance}")
    
    annee_naissance = dt_naissance.year
    duree_requise = DUREES_REQUISES.get(annee_naissance, 172)
    
    # Calcul du taux (avec décote plafonnée à 25% pour RP)
    trimestres_manquants = max(0, duree_requise - trimestres_tous_regimes)
    
    if trimestres_manquants == 0:
        taux = TAUX_PLEIN
        decote = 0.0
    else:
        decote = min(trimestres_manquants * DECOTE_PAR_TRIMESTRE, DECOTE_MAX_RP)
        taux = TAUX_PLEIN - decote
    
    # Calcul de la pension de base
    prorata = min(trimestres_rg, duree_requise) / duree_requise
    pension_base = sam * (taux / 100) * prorata
    
    # Application majoration enfants
    pension_avec_majoration = pension_base * (1 + majoration_enfants / 100)
    
    return {
        "sam": sam,
        "taux": round(taux, 2),
        "decote": round(decote, 2),
        "trimestres_manquants": trimestres_manquants,
        "trimestres_rg": trimestres_rg,
        "duree_requise": duree_requise,
        "prorata": round(prorata, 4),
        "pension_base_annuelle": round(pension_base, 2),
        "majoration_enfants_pct": majoration_enfants,
        "pension_entiere_annuelle": round(pension_avec_majoration, 2),
        "pension_entiere_mensuelle": round(pension_avec_majoration / 12, 2),
        "controles": ["RP_C04", "RP_C05", "RP_C06"]
    }


def calculer_fraction_pension(quotite_travaillee: int) -> Dict:
    """
    Calcule la fraction de pension versée.
    
    Formule: Fraction = 100% - Quotité travaillée
    
    Args:
        quotite_travaillee: Quotité de travail en %
    
    Returns:
        Dict avec fraction et détails
    
    Exemple:
        >>> calculer_fraction_pension(60)
        {'fraction': 40, 'quotite': 60, ...}
    """
    fraction = 100 - quotite_travaillee
    
    # Vérifier limites (20-60%)
    fraction_min = 100 - QUOTITE_MAX  # 20%
    fraction_max = 100 - QUOTITE_MIN  # 60%
    
    valide = fraction_min <= fraction <= fraction_max
    
    return {
        "fraction": fraction,
        "quotite_travaillee": quotite_travaillee,
        "fraction_min": fraction_min,
        "fraction_max": fraction_max,
        "valide": valide,
        "controle": "RP_C07",
        "message": f"Fraction de pension: {fraction}% (quotité travaillée: {quotite_travaillee}%)"
    }


def calculer_montant_rp(pension_entiere_mensuelle: float, fraction: int) -> Dict:
    """
    Calcule le montant de la retraite progressive à verser.
    
    Args:
        pension_entiere_mensuelle: Pension entière mensuelle (€)
        fraction: Fraction de pension en %
    
    Returns:
        Dict avec montant RP et détails
    """
    montant_rp = pension_entiere_mensuelle * (fraction / 100)
    
    return {
        "pension_entiere_mensuelle": round(pension_entiere_mensuelle, 2),
        "fraction": fraction,
        "montant_rp_mensuel": round(montant_rp, 2),
        "montant_rp_annuel": round(montant_rp * 12, 2)
    }


def verifier_eligibilite_complete(
    date_naissance: str,
    trimestres_tous_regimes: int,
    emplois: List[Dict]
) -> Dict:
    """
    Vérifie l'éligibilité complète à la RP (3 conditions).
    
    Args:
        date_naissance: Date de naissance (DD/MM/YYYY ou YYYY-MM-DD)
        trimestres_tous_regimes: Trimestres tous régimes + PRE
        emplois: Liste des emplois (format multi-employeurs)
    
    Returns:
        Dict avec résultat d'éligibilité et détails
    """
    resultats = {
        "eligible": False,
        "conditions": {},
        "controles": [],
        "alertes": []
    }
    
    # CONDITION 1: Âge
    try:
        age_rp_ans, age_rp_mois, date_eligibilite = calculer_age_rp_minimal(date_naissance)
        
        # Calculer âge actuel
        if "/" in date_naissance:
            dt_naissance = datetime.strptime(date_naissance, "%d/%m/%Y")
        else:
            dt_naissance = datetime.strptime(date_naissance, "%Y-%m-%d")
        
        aujourd_hui = datetime.now()
        age_actuel = relativedelta(aujourd_hui, dt_naissance)
        
        age_ok = aujourd_hui >= date_eligibilite
        
        resultats["conditions"]["age"] = {
            "valide": age_ok,
            "age_actuel": f"{age_actuel.years} ans {age_actuel.months} mois",
            "age_minimum_rp": f"{age_rp_ans} ans {age_rp_mois} mois",
            "date_eligibilite": date_eligibilite.strftime("%d/%m/%Y"),
            "controle": "RP_C01"
        }
        
        if not age_ok:
            resultats["controles"].append({
                "id": "RP_C01",
                "type": "ERREUR_CRITIQUE",
                "message": f"Âge insuffisant. Âge minimum: {age_rp_ans} ans {age_rp_mois} mois. Éligibilité à partir du {date_eligibilite.strftime('%d/%m/%Y')}."
            })
    
    except Exception as e:
        resultats["conditions"]["age"] = {
            "valide": False,
            "erreur": str(e)
        }
        return resultats
    
    # CONDITION 2: Durée d'assurance
    duree_check = verifier_duree_assurance(trimestres_tous_regimes)
    resultats["conditions"]["duree"] = duree_check
    
    if not duree_check["eligible"]:
        resultats["controles"].append({
            "id": "RP_C02",
            "type": "ERREUR_CRITIQUE",
            "message": duree_check["message"]
        })
    
    # CONDITION 3: Quotité de travail
    quotite_multi = calculer_quotite_multi_employeurs(emplois)
    quotite_totale = quotite_multi["quotite_totale"]
    quotite_check = verifier_quotite_eligibilite(quotite_totale)
    
    resultats["conditions"]["quotite"] = {
        **quotite_check,
        "multi_employeurs": quotite_multi
    }
    
    if not quotite_check["eligible"]:
        resultats["controles"].append({
            "id": "RP_C03",
            "type": "ERREUR_CRITIQUE",
            "message": quotite_check["message"]
        })
    
    # Alertes métier
    if quotite_totale <= 45 or quotite_totale >= 75:
        resultats["alertes"].append({
            "type": "JAUNE",
            "message": f"Quotité proche de la limite ({quotite_totale}%). Vigilance sur modifications contractuelles."
        })
    
    if len(emplois) > 1:
        resultats["alertes"].append({
            "type": "JAUNE",
            "message": f"{len(emplois)} employeurs détectés. Vérifier totalisation des quotités."
        })
    
    # Résultat global
    resultats["eligible"] = (
        resultats["conditions"]["age"]["valide"] and
        resultats["conditions"]["duree"]["eligible"] and
        resultats["conditions"]["quotite"]["eligible"]
    )
    
    return resultats


def calculer_retraite_progressive_complete(
    date_naissance: str,
    trimestres_tous_regimes: int,
    trimestres_rg: int,
    sam: float,
    emplois: List[Dict],
    majoration_enfants: float = 0.0
) -> Dict:
    """
    Calcul complet de la retraite progressive.
    
    Args:
        date_naissance: Date de naissance
        trimestres_tous_regimes: Trimestres tous régimes + PRE
        trimestres_rg: Trimestres régime général
        sam: Salaire annuel moyen (€)
        emplois: Liste des emplois
        majoration_enfants: Majoration pour enfants (%)
    
    Returns:
        Dict avec tous les calculs et recommandations
    """
    # Vérifier éligibilité
    eligibilite = verifier_eligibilite_complete(date_naissance, trimestres_tous_regimes, emplois)
    
    if not eligibilite["eligible"]:
        return {
            **eligibilite,
            "calculs": None,
            "recommandation": "Non éligible à la retraite progressive"
        }
    
    # Calcul quotité totale
    quotite_multi = eligibilite["conditions"]["quotite"]["multi_employeurs"]
    quotite_totale = quotite_multi["quotite_totale"]
    
    # Calcul pension entière provisoire
    pension_entiere = calculer_pension_entiere_provisoire(
        sam, trimestres_rg, trimestres_tous_regimes, date_naissance, majoration_enfants
    )
    
    # Calcul fraction
    fraction_info = calculer_fraction_pension(quotite_totale)
    
    # Calcul montant RP
    montant_info = calculer_montant_rp(
        pension_entiere["pension_entiere_mensuelle"],
        fraction_info["fraction"]
    )
    
    # Estimation salaire temps partiel (approximatif)
    # On suppose un salaire de 2000€/mois temps complet pour l'exemple
    salaire_tp_estime = 2000 * (quotite_totale / 100)
    revenus_totaux = salaire_tp_estime + montant_info["montant_rp_mensuel"]
    
    return {
        **eligibilite,
        "calculs": {
            "pension_entiere": pension_entiere,
            "fraction": fraction_info,
            "montant_rp": montant_info,
            "revenus_estimes": {
                "salaire_tp_mensuel": round(salaire_tp_estime, 2),
                "pension_rp_mensuelle": montant_info["montant_rp_mensuel"],
                "total_mensuel": round(revenus_totaux, 2)
            }
        },
        "recommandation": "Éligible à la retraite progressive",
        "date_calcul": datetime.now().strftime("%d/%m/%Y %H:%M")
    }


# ========== API HANDLER POUR N8N ==========

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour N8N.
    
    Args:
        params: Dict contenant tous les paramètres nécessaires
    
    Paramètres attendus:
        - date_naissance: str (DD/MM/YYYY ou YYYY-MM-DD)
        - trimestres_tous_regimes: int
        - trimestres_rg: int
        - sam: float
        - emplois: List[Dict] avec clés:
            - heures_tp: float
            - heures_tc: float
            - type_employeur: str ("entreprise", "particulier", "assistante_maternelle")
        - majoration_enfants: float (optionnel, défaut 0)
    
    Returns:
        Dict avec résultat complet
    
    Exemple:
        >>> params = {
        ...     "date_naissance": "15/03/1965",
        ...     "trimestres_tous_regimes": 165,
        ...     "trimestres_rg": 158,
        ...     "sam": 28000,
        ...     "emplois": [{"heures_tp": 24, "heures_tc": 35, "type_employeur": "entreprise"}],
        ...     "majoration_enfants": 0
        ... }
        >>> result = api_handler(params)
        >>> result["eligible"]
        True
    """
    try:
        # Extraction des paramètres
        date_naissance = params.get("date_naissance")
        trimestres_tous_regimes = int(params.get("trimestres_tous_regimes", 0))
        trimestres_rg = int(params.get("trimestres_rg", 0))
        sam = float(params.get("sam", 0))
        emplois = params.get("emplois", [])
        majoration_enfants = float(params.get("majoration_enfants", 0))
        
        # Validation des données
        if not date_naissance:
            return {
                "success": False,
                "erreur": "Date de naissance manquante"
            }
        
        if not emplois:
            return {
                "success": False,
                "erreur": "Au moins un emploi doit être fourni"
            }
        
        # Calcul complet
        resultat = calculer_retraite_progressive_complete(
            date_naissance,
            trimestres_tous_regimes,
            trimestres_rg,
            sam,
            emplois,
            majoration_enfants
        )
        
        return {
            "success": True,
            "eligible": resultat["eligible"],
            "conditions": resultat["conditions"],
            "calculs": resultat.get("calculs"),
            "controles": resultat.get("controles", []),
            "alertes": resultat.get("alertes", []),
            "recommandation": resultat.get("recommandation"),
            "date_calcul": resultat.get("date_calcul"),
            "token_estimate": 500  # Estimation tokens utilisés
        }
    
    except Exception as e:
        return {
            "success": False,
            "erreur": f"Erreur lors du calcul: {str(e)}",
            "type_erreur": type(e).__name__
        }


# ========== FONCTIONS UTILITAIRES ==========

def formater_sortie_client(resultat: Dict) -> str:
    """
    Formate le résultat pour affichage client.
    
    Args:
        resultat: Dict retourné par api_handler
    
    Returns:
        str: Texte formaté pour le client
    """
    if not resultat.get("success"):
        return f"❌ ERREUR: {resultat.get('erreur')}"
    
    output = []
    
    if resultat["eligible"]:
        output.append("✅ VOUS ÊTES ÉLIGIBLE À LA RETRAITE PROGRESSIVE\n")
        
        # Conditions
        output.append("Conditions remplies:")
        cond_age = resultat["conditions"]["age"]
        output.append(f"- ✅ Âge: {cond_age['age_actuel']} (minimum {cond_age['age_minimum_rp']})")
        
        cond_duree = resultat["conditions"]["duree"]
        output.append(f"- ✅ Durée: {cond_duree['trimestres_valides']} trimestres (minimum {cond_duree['trimestres_requis']})")
        
        cond_quotite = resultat["conditions"]["quotite"]
        output.append(f"- ✅ Quotité: {cond_quotite['quotite']}% (entre {cond_quotite['quotite_min']}% et {cond_quotite['quotite_max']}%)")
        
        # Calculs
        if resultat.get("calculs"):
            calculs = resultat["calculs"]
            output.append("\n💰 ESTIMATION RETRAITE PROGRESSIVE:\n")
            
            pension = calculs["pension_entiere"]
            output.append(f"Pension entière provisoire:")
            output.append(f"- SAM: {pension['sam']:,.0f} €")
            output.append(f"- Taux: {pension['taux']}% (décote: {pension['decote']}%)")
            output.append(f"- Montant: {pension['pension_entiere_mensuelle']:,.2f} € brut/mois")
            
            fraction = calculs["fraction"]
            output.append(f"\nFraction de pension:")
            output.append(f"- Quotité travaillée: {fraction['quotite_travaillee']}%")
            output.append(f"- Fraction versée: {fraction['fraction']}%")
            
            montant = calculs["montant_rp"]
            output.append(f"\nMontant RP:")
            output.append(f"- Pension RP mensuelle: {montant['montant_rp_mensuel']:,.2f} € brut")
            
            revenus = calculs["revenus_estimes"]
            output.append(f"\nRevenus totaux estimés:")
            output.append(f"- Salaire temps partiel: {revenus['salaire_tp_mensuel']:,.2f} €")
            output.append(f"- Pension RP: {revenus['pension_rp_mensuelle']:,.2f} €")
            output.append(f"- TOTAL: {revenus['total_mensuel']:,.2f} € brut/mois")
        
        # Alertes
        if resultat.get("alertes"):
            output.append("\n⚠️ POINTS D'ATTENTION:")
            for alerte in resultat["alertes"]:
                output.append(f"- {alerte['message']}")
    
    else:
        output.append("❌ VOUS N'ÊTES PAS ÉLIGIBLE À LA RETRAITE PROGRESSIVE\n")
        output.append("Raison(s):")
        
        for controle in resultat.get("controles", []):
            if controle["type"] == "ERREUR_CRITIQUE":
                output.append(f"- {controle['message']}")
    
    return "\n".join(output)


# ========== EXEMPLES D'UTILISATION ==========

if __name__ == "__main__":
    print("=== EXEMPLE 1: Cas simple mono-employeur ===\n")
    
    params1 = {
        "date_naissance": "15/03/1965",
        "trimestres_tous_regimes": 165,
        "trimestres_rg": 158,
        "sam": 28000,
        "emplois": [
            {"heures_tp": 24, "heures_tc": 35, "type_employeur": "entreprise"}
        ],
        "majoration_enfants": 0
    }
    
    resultat1 = api_handler(params1)
    print(formater_sortie_client(resultat1))
    
    print("\n" + "="*70 + "\n")
    print("=== EXEMPLE 2: Multi-employeurs (particuliers) ===\n")
    
    params2 = {
        "date_naissance": "20/06/1964",
        "trimestres_tous_regimes": 172,
        "trimestres_rg": 164,
        "sam": 22500,
        "emplois": [
            {"heures_tp": 18, "heures_tc": 40, "type_employeur": "particulier"},
            {"heures_tp": 12, "heures_tc": 40, "type_employeur": "particulier"}
        ],
        "majoration_enfants": 10
    }
    
    resultat2 = api_handler(params2)
    print(formater_sortie_client(resultat2))
    
    print("\n" + "="*70 + "\n")
    print("=== EXEMPLE 3: Non éligible (âge insuffisant) ===\n")
    
    params3 = {
        "date_naissance": "10/09/1966",
        "trimestres_tous_regimes": 158,
        "trimestres_rg": 150,
        "sam": 25000,
        "emplois": [
            {"heures_tp": 32, "heures_tc": 45, "type_employeur": "assistante_maternelle"}
        ],
        "majoration_enfants": 0
    }
    
    resultat3 = api_handler(params3)
    print(formater_sortie_client(resultat3))
    
    print("\n" + "="*70 + "\n")
    print("Calculs terminés avec succès !")
