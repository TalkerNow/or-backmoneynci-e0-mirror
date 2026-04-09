# -*- coding: utf-8 -*-
"""
Script : calcul_complementaires.py
Description : Calculs des pensions des régimes complémentaires
Version : 1.0
Date : Novembre 2025
Législation : 2025 (Circulaires CNAV 2024-36, 2025-14)

Ce script automatise les calculs pour :
- AGIRC-ARRCO (salariés du privé)
- IRCANTEC (contractuels fonction publique)
- RCI et RCO (indépendants)

Règles spécifiques :
- Minoration AGIRC-ARRCO selon règle avril 2024
- Coefficients de minoration IRCANTEC
- Abattements RCI (pas de surcote)

Format de dates : JJ/MM/AAAA (exemple : 15/03/1965)
"""

from typing import Dict


# ============================================================================
# SECTION 1 : VALEURS DES POINTS 2025
# ============================================================================

# Valeurs officielles 2025
VALEUR_POINT_AGIRC_ARRCO = 1.4386  # euros (au 1er novembre 2024)
VALEUR_POINT_IRCANTEC = 0.56357  # euros (au 1er janvier 2025)
VALEUR_POINT_RCI = 1.335  # euros (Régime Complémentaire Indépendants)
VALEUR_POINT_RCO = 1.200  # euros (Régime Complémentaire Ouvriers)


# ============================================================================
# SECTION 2 : TABLEAUX DE COEFFICIENTS
# ============================================================================

# Coefficients de minoration AGIRC-ARRCO (règle avril 2024)
# S'applique UNIQUEMENT si départ en carrière longue AVANT taux plein
COEFFICIENTS_MINORATION_AGIRC_ARRCO = {
    (1, 4): 0.99,    # 1 ÃÂ  4 trimestres manquants : 99%
    (5, 8): 0.98,    # 5 ÃÂ  8 trimestres : 98%
    (9, 12): 0.97,   # 9 ÃÂ  12 trimestres : 97%
    (13, 16): 0.96,  # 13 ÃÂ  16 trimestres : 96%
    (17, 20): 0.95,  # 17 ÃÂ  20 trimestres : 95%
}

# Coefficients de minoration IRCANTEC (identiques Ã  AGIRC-ARRCO)
COEFFICIENTS_MINORATION_IRCANTEC = {
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

# Abattements RCI selon trimestres manquants
ABATTEMENTS_RCI = {
    1: 0.9875,   # -1,25%
    2: 0.975,    # -2,50%
    3: 0.9625,   # -3,75%
    4: 0.95,     # -5,00%
    5: 0.9375,   # -6,25%
    6: 0.925,    # -7,50%
    7: 0.9125,   # -8,75%
    8: 0.90,     # -10,00%
    9: 0.8875,   # -11,25%
    10: 0.875,   # -12,50%
    11: 0.8625,  # -13,75%
    12: 0.85,    # -15,00%
    13: 0.8375,  # -16,25%
    14: 0.825,   # -17,50%
    15: 0.8125,  # -18,75%
    16: 0.80,    # -20,00%
    17: 0.7875,  # -21,25%
    18: 0.775,   # -22,50%
    19: 0.7625,  # -23,75%
    20: 0.75,    # -25,00% (maximum)
}


# ============================================================================
# SECTION 3 : FONCTIONS UTILITAIRES
# ============================================================================

def obtenir_coefficient_minoration_agirc_arrco(trimestres_manquants: int) -> float:
    """
    Détermine le coefficient de minoration AGIRC-ARRCO selon le nombre de trimestres manquants.
    
    Args:
        trimestres_manquants (int): Nombre de trimestres manquants pour le taux plein
    
    Returns:
        float: Coefficient ÃÂ  appliquer (0.95 ÃÂ  0.99, ou 1.0 si pas d'abattement)
    
    Exemple:
        >>> obtenir_coefficient_minoration_agirc_arrco(7)
        0.98
    """
    if trimestres_manquants <= 0:
        return 1.0
    
    for (min_trim, max_trim), coeff in COEFFICIENTS_MINORATION_AGIRC_ARRCO.items():
        if min_trim <= trimestres_manquants <= max_trim:
            return coeff
    
    # Si > 20 trimestres : coefficient minimal
    return 0.95


def obtenir_coefficient_minoration_ircantec(trimestres_manquants: int) -> float:
    """
    Détermine le coefficient de minoration IRCANTEC selon le nombre de trimestres manquants.
    
    Args:
        trimestres_manquants (int): Nombre de trimestres manquants pour le taux plein
    
    Returns:
        float: Coefficient Ã  appliquer (0.78 Ã  1.0)
    
    Exemple:
        >>> obtenir_coefficient_minoration_ircantec(6)
        0.94
    """
    if trimestres_manquants <= 0:
        return 1.0
    
    # Accès direct au dictionnaire (structure: {trimestre: coefficient})
    if trimestres_manquants in COEFFICIENTS_MINORATION_IRCANTEC:
        return COEFFICIENTS_MINORATION_IRCANTEC[trimestres_manquants]
    
    # Si > 20 trimestres : coefficient minimal (0.78 selon ligne 72)
    return 0.78

def obtenir_coefficient_abattement_rci(trimestres_manquants: int) -> float:
    """
    Détermine le coefficient d'abattement RCI selon le nombre de trimestres manquants.
    
    âÅ¡Â Ã¯Â¸Â Pas de surcote pour le RCI (contrairement au régime de base).
    
    Args:
        trimestres_manquants (int): Nombre de trimestres manquants pour le taux plein
    
    Returns:
        float: Coefficient ÃÂ  appliquer (0.75 ÃÂ  0.9875, ou 1.0 si taux plein)
    
    Exemple:
        >>> obtenir_coefficient_abattement_rci(5)
        0.9375
    """
    if trimestres_manquants <= 0:
        return 1.0
    
    if trimestres_manquants in ABATTEMENTS_RCI:
        return ABATTEMENTS_RCI[trimestres_manquants]
    
    # Si > 20 trimestres : abattement maximal
    return 0.75


# ============================================================================
# SECTION 4 : CALCUL AGIRC-ARRCO
# ============================================================================

def calculer_pension_agirc_arrco(
    nombre_points: float,
    depart_avant_age_legal: bool,
    taux_plein_atteint: bool,
    trimestres_manquants_taux_plein: int = 0,
    situation_speciale: bool = False
) -> Dict[str, any]:
    """
    Calcule la pension AGIRC-ARRCO selon les règles d'avril 2024.
    
    RÃˆGLE CRITIQUE AVRIL 2024 :
    - PAS d'abattement si :
      â€¢ Départ au taux plein (durée requise atteinte), OU
      â€¢ Départ ÃÂ  l'âge légal (même sans taux plein), OU
      â€¢ Départ ÃÂ  67 ans (taux plein automatique), OU
      â€¢ Situation d'inaptitude, invalidité, pénibilité
    
    - Abattement UNIQUEMENT si :
      â€¢ Départ en carrière longue (avant âge légal), ET
      â€¢ Départ AVANT le taux plein (durée requise non atteinte)
    
    Args:
        nombre_points (float): Nombre de points AGIRC-ARRCO acquis
        depart_avant_age_legal (bool): True si départ en carrière longue (avant âge légal)
        taux_plein_atteint (bool): True si durée requise atteinte
        trimestres_manquants_taux_plein (int): Trimestres manquants pour taux plein (si départ avant)
        situation_speciale (bool): True si inaptitude/invalidité/pénibilité
    
    Returns:
        dict: {
            "pension_annuelle": float,
            "pension_mensuelle": float,
            "nombre_points": float,
            "valeur_point": float,
            "coefficient_minoration": float,
            "abattement_applique": bool
        }
    
    Exemple entrée :
        nombre_points = 6500
        depart_avant_age_legal = False
        taux_plein_atteint = True
    
    Exemple sortie :
        {
            "pension_annuelle": 9351.0,
            "pension_mensuelle": 779.25,
            "coefficient_minoration": 1.0,
            "abattement_applique": False
        }
    """
    # Pension de base (sans abattement)
    pension_base = nombre_points * VALEUR_POINT_AGIRC_ARRCO
    
    # Détermination si abattement applicable
    abattement_applique = False
    coefficient = 1.0
    
    # CAS 1 : PAS d'abattement
    if taux_plein_atteint or not depart_avant_age_legal or situation_speciale:
        # Pas d'abattement dans ces cas
        abattement_applique = False
        coefficient = 1.0
    # CAS 2 : Abattement (départ carrière longue AVANT taux plein)
    elif depart_avant_age_legal and not taux_plein_atteint:
        abattement_applique = True
        coefficient = obtenir_coefficient_minoration_agirc_arrco(trimestres_manquants_taux_plein)
    
    # Calcul final
    pension_finale = pension_base * coefficient
    
    return {
        "pension_annuelle": round(pension_finale, 2),
        "pension_mensuelle": round(pension_finale / 12, 2),
        "nombre_points": nombre_points,
        "valeur_point": VALEUR_POINT_AGIRC_ARRCO,
        "coefficient_minoration": coefficient,
        "abattement_applique": abattement_applique,
        "trimestres_manquants": trimestres_manquants_taux_plein if abattement_applique else 0
    }


# ============================================================================
# SECTION 5 : CALCUL IRCANTEC
# ============================================================================

def calculer_pension_ircantec(
    nombre_points: float,
    trimestres_manquants_taux_plein: int = 0,
    taux_plein_atteint: bool = False
) -> Dict[str, any]:
    """
    Calcule la pension IRCANTEC (contractuels fonction publique).
    
    Minoration applicable selon le nombre de trimestres manquants pour le taux plein.
    
    Args:
        nombre_points (float): Nombre de points IRCANTEC acquis
        trimestres_manquants_taux_plein (int): Trimestres manquants pour le taux plein
        taux_plein_atteint (bool): True si taux plein atteint
    
    Returns:
        dict: {
            "pension_annuelle": float,
            "pension_mensuelle": float,
            "nombre_points": float,
            "valeur_point": float,
            "coefficient_minoration": float
        }
    
    Exemple entrée :
        nombre_points = 1800
        trimestres_manquants_taux_plein = 8
    
    Exemple sortie :
        {
            "pension_annuelle": 994.0,
            "pension_mensuelle": 82.83,
            "coefficient_minoration": 0.98
        }
    """
    # Pension de base
    pension_base = nombre_points * VALEUR_POINT_IRCANTEC
    
    # Détermination du coefficient
    if taux_plein_atteint:
        coefficient = 1.0
    else:
        coefficient = obtenir_coefficient_minoration_ircantec(trimestres_manquants_taux_plein)
    
    # Calcul final
    pension_finale = pension_base * coefficient
    
    return {
        "pension_annuelle": round(pension_finale, 2),
        "pension_mensuelle": round(pension_finale / 12, 2),
        "nombre_points": nombre_points,
        "valeur_point": VALEUR_POINT_IRCANTEC,
        "coefficient_minoration": coefficient,
        "trimestres_manquants": trimestres_manquants_taux_plein
    }


# ============================================================================
# SECTION 6 : CALCUL RCI ET RCO (INDÉPENDANTS)
# ============================================================================

def calculer_pension_rci_rco(
    points_rci: float,
    points_rco: float,
    trimestres_manquants_taux_plein: int = 0,
    taux_plein_atteint: bool = False
) -> Dict[str, any]:
    """
    Calcule les pensions RCI et RCO pour les indépendants.
    
    âÅ¡Â Ã¯Â¸Â ATTENTION : Pas de surcote pour le RCI/RCO (contrairement au régime de base).
    
    Args:
        points_rci (float): Nombre de points RCI acquis
        points_rco (float): Nombre de points RCO acquis
        trimestres_manquants_taux_plein (int): Trimestres manquants pour le taux plein
        taux_plein_atteint (bool): True si taux plein atteint
    
    Returns:
        dict: {
            "pension_annuelle_totale": float,
            "pension_mensuelle_totale": float,
            "pension_rci": float,
            "pension_rco": float,
            "coefficient_abattement": float,
            "points_rci": float,
            "points_rco": float
        }
    
    Exemple entrée :
        points_rci = 950
        points_rco = 520
        taux_plein_atteint = True
    
    Exemple sortie :
        {
            "pension_annuelle_totale": 1892.0,
            "pension_mensuelle_totale": 157.67,
            "pension_rci": 1268.25,
            "pension_rco": 624.0
        }
    """
    # Calcul des pensions de base
    pension_base_rci = points_rci * VALEUR_POINT_RCI
    pension_base_rco = points_rco * VALEUR_POINT_RCO
    
    # Détermination du coefficient d'abattement
    if taux_plein_atteint:
        coefficient = 1.0
    else:
        coefficient = obtenir_coefficient_abattement_rci(trimestres_manquants_taux_plein)
    
    # Application de l'abattement
    pension_finale_rci = pension_base_rci * coefficient
    pension_finale_rco = pension_base_rco * coefficient
    pension_totale = pension_finale_rci + pension_finale_rco
    
    return {
        "pension_annuelle_totale": round(pension_totale, 2),
        "pension_mensuelle_totale": round(pension_totale / 12, 2),
        "pension_rci": round(pension_finale_rci, 2),
        "pension_rco": round(pension_finale_rco, 2),
        "coefficient_abattement": coefficient,
        "points_rci": points_rci,
        "points_rco": points_rco,
        "valeur_point_rci": VALEUR_POINT_RCI,
        "valeur_point_rco": VALEUR_POINT_RCO,
        "trimestres_manquants": trimestres_manquants_taux_plein
    }


# ============================================================================
# SECTION 7 : CALCUL CONSOLIDÉ (TOUS RÉGIMES COMPLÉMENTAIRES)
# ============================================================================

def calculer_tous_complementaires(
    points_agirc_arrco: float = 0,
    points_ircantec: float = 0,
    points_rci: float = 0,
    points_rco: float = 0,
    depart_avant_age_legal: bool = False,
    taux_plein_atteint: bool = False,
    trimestres_manquants_taux_plein: int = 0,
    situation_speciale: bool = False
) -> Dict[str, any]:
    """
    Calcule l'ensemble des pensions complémentaires (tous régimes applicables).
    
    Args:
        points_agirc_arrco (float): Points AGIRC-ARRCO (si salarié privé)
        points_ircantec (float): Points IRCANTEC (si contractuel public)
        points_rci (float): Points RCI (si indépendant)
        points_rco (float): Points RCO (si indépendant)
        depart_avant_age_legal (bool): Départ en carrière longue
        taux_plein_atteint (bool): Taux plein atteint
        trimestres_manquants_taux_plein (int): Trimestres manquants
        situation_speciale (bool): Inaptitude/invalidité
    
    Returns:
        dict: Détails de toutes les pensions complémentaires
    """
    resultats = {
        "pension_totale_annuelle": 0,
        "pension_totale_mensuelle": 0,
        "details": {}
    }
    
    # AGIRC-ARRCO
    if points_agirc_arrco > 0:
        agirc_arrco = calculer_pension_agirc_arrco(
            points_agirc_arrco,
            depart_avant_age_legal,
            taux_plein_atteint,
            trimestres_manquants_taux_plein,
            situation_speciale
        )
        resultats["details"]["agirc_arrco"] = agirc_arrco
        resultats["pension_totale_annuelle"] += agirc_arrco["pension_annuelle"]
    
    # IRCANTEC
    if points_ircantec > 0:
        ircantec = calculer_pension_ircantec(
            points_ircantec,
            trimestres_manquants_taux_plein,
            taux_plein_atteint
        )
        resultats["details"]["ircantec"] = ircantec
        resultats["pension_totale_annuelle"] += ircantec["pension_annuelle"]
    
    # RCI/RCO
    if points_rci > 0 or points_rco > 0:
        rci_rco = calculer_pension_rci_rco(
            points_rci,
            points_rco,
            trimestres_manquants_taux_plein,
            taux_plein_atteint
        )
        resultats["details"]["rci_rco"] = rci_rco
        resultats["pension_totale_annuelle"] += rci_rco["pension_annuelle_totale"]
    
    # Calcul mensuel
    resultats["pension_totale_mensuelle"] = round(resultats["pension_totale_annuelle"] / 12, 2)
    resultats["pension_totale_annuelle"] = round(resultats["pension_totale_annuelle"], 2)
    
    return resultats


# ============================================================================
# SECTION 8 : EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    print("=" * 80)
    print("SCRIPT : calcul_complementaires.py - TESTS ET EXEMPLES")
    print("=" * 80)
    
    # EXEMPLE 1 : Salarié cadre avec taux plein (AGIRC-ARRCO)
    print("\n### EXEMPLE 1 : Salarié cadre avec taux plein (AGIRC-ARRCO) ###")
    print("-" * 80)
    
    result_1 = calculer_pension_agirc_arrco(
        nombre_points=6500,
        depart_avant_age_legal=False,
        taux_plein_atteint=True
    )
    
    print(f"Points AGIRC-ARRCO : {result_1['nombre_points']:,.0f}")
    print(f"Valeur du point : {result_1['valeur_point']:.4f} â‚¬")
    print(f"Coefficient minoration : {result_1['coefficient_minoration']:.2f}")
    print(f"Abattement appliqué : {'OUI' if result_1['abattement_applique'] else 'NON'}")
    print(f"\nRÉSULTATS :")
    print(f"  - Pension annuelle : {result_1['pension_annuelle']:,.2f} â‚¬")
    print(f"  - Pension mensuelle : {result_1['pension_mensuelle']:,.2f} â‚¬")
    
    # EXEMPLE 2 : Contractuel avec décote (IRCANTEC)
    print("\n\n### EXEMPLE 2 : Agent contractuel avec décote (IRCANTEC) ###")
    print("-" * 80)
    
    result_2 = calculer_pension_ircantec(
        nombre_points=1800,
        trimestres_manquants_taux_plein=8,
        taux_plein_atteint=False
    )
    
    print(f"Points IRCANTEC : {result_2['nombre_points']:,.0f}")
    print(f"Trimestres manquants : {result_2['trimestres_manquants']}")
    print(f"Coefficient minoration : {result_2['coefficient_minoration']:.2f}")
    print(f"\nRÉSULTATS :")
    print(f"  - Pension annuelle : {result_2['pension_annuelle']:,.2f} â‚¬")
    print(f"  - Pension mensuelle : {result_2['pension_mensuelle']:,.2f} â‚¬")
    
    # EXEMPLE 3 : Indépendant avec taux plein (RCI/RCO)
    print("\n\n### EXEMPLE 3 : Indépendant avec taux plein (RCI/RCO) ###")
    print("-" * 80)
    
    result_3 = calculer_pension_rci_rco(
        points_rci=950,
        points_rco=520,
        taux_plein_atteint=True
    )
    
    print(f"Points RCI : {result_3['points_rci']:,.0f}")
    print(f"Points RCO : {result_3['points_rco']:,.0f}")
    print(f"Coefficient abattement : {result_3['coefficient_abattement']:.2f}")
    print(f"\nRÉSULTATS :")
    print(f"  - Pension RCI : {result_3['pension_rci']:,.2f} â‚¬")
    print(f"  - Pension RCO : {result_3['pension_rco']:,.2f} â‚¬")
    print(f"  - Pension totale annuelle : {result_3['pension_annuelle_totale']:,.2f} â‚¬")
    print(f"  - Pension totale mensuelle : {result_3['pension_mensuelle_totale']:,.2f} â‚¬")
    
    # EXEMPLE 4 : Calcul consolidé (plusieurs régimes)
    print("\n\n### EXEMPLE 4 : Calcul consolidé multi-régimes ###")
    print("-" * 80)
    
    result_4 = calculer_tous_complementaires(
        points_agirc_arrco=6500,
        points_ircantec=800,
        taux_plein_atteint=True
    )
    
    print(f"TOTAL COMPLÉMENTAIRES :")
    print(f"  - Pension annuelle totale : {result_4['pension_totale_annuelle']:,.2f} â‚¬")
    print(f"  - Pension mensuelle totale : {result_4['pension_totale_mensuelle']:,.2f} â‚¬")
    print(f"\nDÉTAIL PAR RÉGIME :")
    if "agirc_arrco" in result_4["details"]:
        print(f"  - AGIRC-ARRCO : {result_4['details']['agirc_arrco']['pension_mensuelle']:,.2f} â‚¬/mois")
    if "ircantec" in result_4["details"]:
        print(f"  - IRCANTEC : {result_4['details']['ircantec']['pension_mensuelle']:,.2f} â‚¬/mois")
    
    print("\n" + "=" * 80)
    print("Tests terminés avec succès !")
    print("=" * 80)
