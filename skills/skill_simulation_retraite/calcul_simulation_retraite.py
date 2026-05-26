"""
========================================
SKILL : Simulation Retraite Multi-Régimes
========================================

Version : 1.0
Date : 2025-04-28
Type : Skill Calcul - 3 Scénarios (H1/H2/H3)

Régimes couverts :
- CNAV (Régime Général)
- AGIRC-ARRCO (retraite complémentaire cadre/non-cadre)
- IRCANTEC (contractuels de la fonction publique)
- RCI (régimes spéciaux / complémentaires individuels)

Fonctions principales :
- Calcul des 3 scénarios : H1 (départ légal), H2 (taux plein), H3 (optimal)
- Projection sur 20 ans avec cumuls nominaux et réels
- Point d'équilibre (breakeven) H1 vs H3
- Impact inflation
- Prise en compte des trimestres étrangers (totalisation UE)

Point d'entrée N8N : api_handler(params)
Dépendances : python-dateutil uniquement (pas d'openpyxl, pas de pandas)
"""

from typing import Dict, List, Tuple, Optional
from datetime import date, datetime
from dateutil.relativedelta import relativedelta
from math import ceil
import json

# ============================================================================
# CONSTANTES 2025
# ============================================================================

TAUX_PLEIN = 0.50
TAUX_MINIMAL = 0.375
DECOTE_PAR_TRIM = 0.0125
VALEUR_POINT_AGIRC_2025 = 1.4384
VALEUR_POINT_IRCANTEC_2025 = 0.47
VALEUR_POINT_RCI_2025 = 1.351
TAUX_PRELEVEMENT_NET = 0.091   # CSG + CRDS + CASA approximation
AGE_LEGAL_ANS = 64             # post-réforme 2023 (loi Borne)
AGE_TAUX_PLEIN_AUTO = 67
SURCOTE_PAR_TRIM = 0.0125      # +1,25 % par trim. au-delà de l'âge légal et de la durée requise
TAUX_REVERSION_CNAV = 0.54     # CNAV / régime général
TAUX_REVERSION_AGIRC = 0.60    # AGIRC-ARRCO
TAUX_REVERSION_IRCANTEC = 0.50
# Source : Circulaire Cnav 2026-07 du 05/03/2026 (loi n°2025-1403 du 30/12/2025 —
# suspension de la réforme 2023). Effet retraite ≥ 01/09/2026.
# NB : pour 1965, jan-mars = 170, avril-déc = 171. La résolution mensuelle
# nécessite d'appeler get_duree_requise(annee, mois) plutôt que get(annee).
DUREES_REQUISES = {
    1958: 167,
    1959: 167,
    1960: 167,
    1961: 168,  # 1961 jan-août ; sept-déc = 169 (résolution mensuelle nécessaire)
    1962: 169,
    1963: 170,
    1964: 170,
    1965: 170,  # jan-mars ; avril-déc = 171 (résolution mensuelle nécessaire)
}
# 1966+ → 172 (palier final atteint dès la génération 1966 sous la suspension 2026)

# ============================================================================
# UTILITAIRES DATES
# ============================================================================

def parse_date(s: str) -> date:
    """
    Parse une date depuis une chaîne.
    Accepte "YYYY-MM-DD" ou "DD/MM/YYYY".

    Returns:
        date object
    """
    s = s.strip()
    if '-' in s and len(s) == 10 and s[4] == '-':
        return datetime.strptime(s, '%Y-%m-%d').date()
    elif '/' in s:
        return datetime.strptime(s, '%d/%m/%Y').date()
    else:
        # Tentative ISO par défaut
        return datetime.strptime(s, '%Y-%m-%d').date()


def get_duree_requise(annee_naissance: int, mois_naissance: int = 1) -> int:
    """
    Retourne la durée d'assurance requise (en trimestres) pour une génération.

    Résolution mensuelle pour 1965 (jan-mars: 170, avril-déc: 171) per
    Circulaire Cnav 2026-07.

    Returns:
        int : nombre de trimestres requis (défaut 172 pour 1966+)
    """
    if annee_naissance == 1965 and mois_naissance and mois_naissance >= 4:
        return 171
    if annee_naissance == 1961 and mois_naissance and mois_naissance >= 9:
        return 169
    return DUREES_REQUISES.get(annee_naissance, 172)


def age_en_annees(date_naissance: date, reference: date) -> float:
    """
    Calcule l'âge en années fractionnaires à une date de référence.
    Utilise relativedelta pour la précision mois/jours.

    Returns:
        float : âge en années (ex: 64.25 pour 64 ans et 3 mois)
    """
    rd = relativedelta(reference, date_naissance)
    return rd.years + rd.months / 12.0 + rd.days / 365.25


def trimestres_acquis_at_date(
    trimestres_actuels: int,
    date_reference: date,
    date_cible: date
) -> int:
    """
    Projette le nombre de trimestres acquis à une date cible.
    Ajoute max(0, months_diff // 3) trimestres supplémentaires.
    Suppose 4 trimestres/an.

    Returns:
        int : trimestres projetés à date_cible
    """
    rd = relativedelta(date_cible, date_reference)
    months_diff = rd.years * 12 + rd.months
    trimestres_supplementaires = max(0, months_diff // 3)
    return trimestres_actuels + trimestres_supplementaires


# ============================================================================
# CALCULS CNAV
# ============================================================================

def calcul_taux_cnav(
    trimestres_acquis: int,
    duree_requise: int,
    age_depart_ans: float
) -> float:
    """
    Calcule le taux de liquidation CNAV.

    - Taux plein (0.50) si trimestres_acquis >= duree_requise OU age >= 67
    - Sinon : max(0.50 - min(trim_manquants, 20) * 0.0125, 0.375)

    Returns:
        float : taux de liquidation
    """
    if trimestres_acquis >= duree_requise or age_depart_ans >= AGE_TAUX_PLEIN_AUTO:
        return TAUX_PLEIN

    trim_manquants = duree_requise - trimestres_acquis
    decote = min(trim_manquants, 20) * DECOTE_PAR_TRIM
    return max(TAUX_PLEIN - decote, TAUX_MINIMAL)


def calcul_pension_cnav(
    sam_annuel: float,
    taux: float,
    trimestres_acquis_rg: int,
    duree_requise: int
) -> float:
    """
    Calcule la pension mensuelle brute CNAV.

    Formule : SAM * taux * min(trim_rg / duree_requise, 1.0) / 12

    Returns:
        float : pension mensuelle brute CNAV, arrondie à 2 décimales
    """
    prorata = min(trimestres_acquis_rg / duree_requise, 1.0)
    pension = sam_annuel * taux * prorata / 12
    return round(pension, 2)


def calcul_surcote(
    trim_rg_at_depart: int,
    duree_requise: int,
    date_depart: date,
    date_age_legal: date,
) -> Tuple[float, int]:
    """
    Surcote CNAV : +1,25 % par trimestre cotisé après l'âge légal au-delà de
    la durée requise. Approximation pragmatique : compte les trimestres acquis
    projetés au-delà de duree_requise, à condition que date_depart >= date_age_legal.

    Returns:
        Tuple[coef_surcote, nb_trim_surcote]
        - coef_surcote ∈ [1.00, ~1.40] à multiplier à la pension CNAV
        - nb_trim_surcote pour traçabilité
    """
    if date_depart < date_age_legal:
        return (1.00, 0)
    if trim_rg_at_depart <= duree_requise:
        return (1.00, 0)
    months_after_legal = (date_depart.year - date_age_legal.year) * 12 + (date_depart.month - date_age_legal.month)
    trim_after_legal = max(0, months_after_legal // 3)
    trim_surplus = trim_rg_at_depart - duree_requise
    trim_surcote = min(trim_surplus, trim_after_legal)
    coef = round(1 + trim_surcote * SURCOTE_PAR_TRIM, 4)
    return (coef, trim_surcote)


def calcul_reversion(
    pension_cnav_mensuelle: float,
    pension_agirc_mensuelle: float,
    pension_ircantec_mensuelle: float,
    statut_marital: Optional[str],
) -> Dict:
    """
    Pension de réversion estimée pour le conjoint survivant (≥55 ans).

    Règles simplifiées :
    - CNAV : 54 % de la pension du défunt (sous conditions ressources)
    - AGIRC-ARRCO : 60 % (sans condition ressources)
    - IRCANTEC : 50 %
    - Réversion CNAV réservée aux mariés (pas PACS, pas concubinage)

    Returns:
        Dict avec {applicable, cnav, agirc, ircantec, total, note}
    """
    statut = (statut_marital or "").lower()
    # Reconnaît "married" (EN), "marié(e)", "marie(e)", "pacs", "pacsé"
    import re as _re
    is_married = bool(_re.match(r"^marr?i", statut))
    is_pacs = "pacs" in statut
    applicable = is_married or is_pacs
    if not applicable:
        return {
            "applicable": False,
            "cnav": 0, "agirc": 0, "ircantec": 0, "total": 0,
            "note": "Pension de réversion non applicable (statut marital : célibataire/divorcé)."
        }
    rev_cnav = round(pension_cnav_mensuelle * TAUX_REVERSION_CNAV, 2) if is_married else 0
    rev_agirc = round(pension_agirc_mensuelle * TAUX_REVERSION_AGIRC, 2)
    rev_ircantec = round(pension_ircantec_mensuelle * TAUX_REVERSION_IRCANTEC, 2) if is_married else 0
    total = round(rev_cnav + rev_agirc + rev_ircantec, 2)
    return {
        "applicable": True,
        "cnav": rev_cnav,
        "agirc": rev_agirc,
        "ircantec": rev_ircantec,
        "total": total,
        "note": (
            f"Pension de réversion estimée pour le conjoint survivant (≥55 ans) : "
            f"{rev_cnav} € CNAV (54 %, sous conditions ressources) + {rev_agirc} € AGIRC-ARRCO (60 %, sans condition) "
            f"+ {rev_ircantec} € IRCANTEC = {total} €/mois bruts. "
            f"{'Réversion CNAV non applicable au PACS.' if is_pacs else ''} "
            f"Estimation indicative — règles définitives à valider avec la CARSAT."
        ).strip()
    }


def calcul_date_taux_plein(
    date_naissance: date,
    trimestres_actuels_tous_regimes: int,
    date_reference: date
) -> date:
    """
    Calcule la date à laquelle le client atteindra le taux plein par la durée.

    Returns:
        date : date d'atteinte du taux plein (peut être dans le passé si déjà atteint)
    """
    duree_requise = get_duree_requise(date_naissance.year)
    trim_manquants = max(0, duree_requise - trimestres_actuels_tous_regimes)
    mois_supplementaires = ceil(trim_manquants * 3)
    return date_reference + relativedelta(months=mois_supplementaires)


# ============================================================================
# CALCULS AGIRC-ARRCO
# ============================================================================

def calcul_coefficient_agirc(
    date_depart: date,
    date_taux_plein: date,
    age_depart_ans: float,
    is_progressive: bool = False
) -> Tuple[float, int]:
    """
    Calcule le coefficient d'abattement AGIRC-ARRCO.

    - Retourne (1.00, 0) si :
        * age >= 67 ans
        * retraite progressive
        * départ >= date_taux_plein + 12 mois (bonus fidélité)
    - Retourne (0.90, 36) sinon (malus 10% pendant 36 mois)

    Returns:
        Tuple[float, int] : (coefficient, durée_malus_en_mois)
    """
    if age_depart_ans >= AGE_TAUX_PLEIN_AUTO:
        return (1.00, 0)

    if is_progressive:
        return (1.00, 0)

    # Bonus fidélité : si départ >= date_taux_plein + 12 mois
    date_bonus = date_taux_plein + relativedelta(months=12)
    if date_depart >= date_bonus:
        return (1.00, 0)

    # Malus 10% pendant 36 mois
    return (0.90, 36)


# ============================================================================
# CALCUL D'UN SCÉNARIO
# ============================================================================

def calcul_scenario(
    code: str,
    date_depart_str: str,
    date_naissance: date,
    date_reference: date,
    date_taux_plein: date,
    sam_annuel: float,
    trimestres_actuels_rg: int,
    trimestres_actuels_tous_regimes: int,
    duree_requise: int,
    agirc_points: float,
    agirc_valeur_point: float,
    ircantec_points: float,
    ircantec_valeur_point: float,
    rci_points: float,
    rci_valeur_point: float,
    is_progressive: bool = False
) -> Dict:
    """
    Calcule tous les éléments d'un scénario de départ (H1, H2 ou H3).

    Returns:
        Dict : résultat complet du scénario
    """
    date_depart = parse_date(date_depart_str)

    # Projection des trimestres à la date de départ
    trim_rg_at_depart = trimestres_acquis_at_date(
        trimestres_actuels_rg, date_reference, date_depart
    )
    trim_tous_at_depart = trimestres_acquis_at_date(
        trimestres_actuels_tous_regimes, date_reference, date_depart
    )

    # Âge au départ
    age_depart = age_en_annees(date_naissance, date_depart)
    rd_age = relativedelta(date_depart, date_naissance)
    age_depart_annees = rd_age.years
    age_depart_mois_total = rd_age.years * 12 + rd_age.months

    # Taux CNAV (basé sur trimestres tous régimes pour le taux plein)
    taux_cnav = calcul_taux_cnav(trim_tous_at_depart, duree_requise, age_depart)

    # Décote en pourcentage
    decote_trims = max(0, min(duree_requise - trim_tous_at_depart, 20)) if trim_tous_at_depart < duree_requise and age_depart < AGE_TAUX_PLEIN_AUTO else 0
    decote_pct = round(decote_trims * DECOTE_PAR_TRIM * 100, 2)

    # Pension CNAV (prorata basé sur trimestres RG uniquement)
    pension_cnav = calcul_pension_cnav(sam_annuel, taux_cnav, trim_rg_at_depart, duree_requise)

    # Coefficient AGIRC
    coeff_agirc, coeff_duree_mois = calcul_coefficient_agirc(
        date_depart, date_taux_plein, age_depart, is_progressive
    )

    # Pensions complémentaires
    pension_agirc = round(agirc_points * agirc_valeur_point * coeff_agirc / 12, 2)
    pension_ircantec = round(ircantec_points * ircantec_valeur_point / 12, 2)
    pension_rci = round(rci_points * rci_valeur_point / 12, 2)

    # Totaux
    pension_totale_brute = round(pension_cnav + pension_agirc + pension_ircantec + pension_rci, 2)
    pension_totale_nette = round(pension_totale_brute * (1 - TAUX_PRELEVEMENT_NET), 2)

    return {
        "code": code,
        "date_depart": date_depart_str,
        "age_depart_annees": age_depart_annees,
        "age_depart_mois": age_depart_mois_total,
        "trimestres_rg_at_depart": trim_rg_at_depart,
        "trimestres_tous_at_depart": trim_tous_at_depart,
        "duree_requise": duree_requise,
        "taux_cnav": round(taux_cnav, 4),
        "decote_pct": decote_pct,
        "pension_cnav_mensuelle": pension_cnav,
        "coeff_agirc": coeff_agirc,
        "coeff_agirc_duree_mois": coeff_duree_mois,
        "pension_agirc_mensuelle": pension_agirc,
        "pension_ircantec_mensuelle": pension_ircantec,
        "pension_rci_mensuelle": pension_rci,
        "pension_totale_brute_mensuelle": pension_totale_brute,
        "pension_totale_nette_mensuelle": pension_totale_nette,
    }


# ============================================================================
# PROJECTION 20 ANS
# ============================================================================

def calcul_projection_20ans(
    scenarios: Dict,
    date_naissance: date,
    date_reference: date,
    agirc_points: float,
    agirc_valeur_point: float,
    inflation: float
) -> List[Dict]:
    """
    Calcule les cumuls nominaux et réels pour des horizons de 1 à 20 ans.

    Applique le coefficient AGIRC mois par mois (malus pendant coeff_duree_mois,
    puis coefficient 1.00 ensuite).

    Returns:
        List[Dict] : liste de dicts par horizon avec cumuls H1/H2/H3
    """
    horizons = [1, 2, 3, 5, 8, 10, 12, 15, 18, 20]

    # Pension AGIRC au taux plein (sans malus)
    pension_agirc_plein = round(agirc_points * agirc_valeur_point / 12, 2)

    resultats = []

    for annees in horizons:
        row = {
            "horizon": annees,
            "annee": date_reference.year + annees,
        }

        for code in ["H1", "H2", "H3"]:
            if code not in scenarios:
                row[f"{code}_cumul_nominal"] = None
                row[f"{code}_cumul_reel"] = None
                continue

            sc = scenarios[code]
            date_depart = parse_date(sc["date_depart"])

            # Pensions de base (hors AGIRC)
            pension_cnav = sc["pension_cnav_mensuelle"]
            pension_ircantec = sc["pension_ircantec_mensuelle"]
            pension_rci = sc["pension_rci_mensuelle"]
            coeff_agirc = sc["coeff_agirc"]
            coeff_duree_mois = sc["coeff_agirc_duree_mois"]

            # Calcul du cumul mois par mois de date_reference jusqu'à l'horizon
            date_fin_horizon = date_reference + relativedelta(years=annees)

            cumul_nominal = 0.0
            mois_depuis_depart = 0  # reset per (horizon, scenario) pair

            # Itération mois par mois depuis date_reference
            current = date_reference

            while current < date_fin_horizon:
                # Ne compter que si on est à partir de la date de départ
                if current >= date_depart:
                    # Calculer le coefficient AGIRC applicable ce mois
                    if coeff_duree_mois > 0 and mois_depuis_depart < coeff_duree_mois:
                        coeff_mois = 0.90
                    else:
                        coeff_mois = 1.00

                    pension_agirc_mois = round(pension_agirc_plein * coeff_mois, 2)
                    pension_brute_mois = pension_cnav + pension_agirc_mois + pension_ircantec + pension_rci
                    pension_nette_mois = round(pension_brute_mois * (1 - TAUX_PRELEVEMENT_NET), 2)

                    cumul_nominal += pension_nette_mois
                    mois_depuis_depart += 1

                current = current + relativedelta(months=1)

            cumul_reel = round(cumul_nominal / ((1 + inflation) ** annees), 2)

            row[f"{code}_cumul_nominal"] = round(cumul_nominal, 2)
            row[f"{code}_cumul_reel"] = cumul_reel

        resultats.append(row)

    return resultats


# ============================================================================
# POINT D'ÉQUILIBRE (BREAKEVEN)
# ============================================================================

def calcul_breakeven(scenarios: Dict, date_reference: date) -> Dict:
    """
    Calcule le point d'équilibre entre H1 (départ légal) et H3 (optimal).

    Returns:
        Dict : résultat du breakeven avec mois, date, capital_perdu, gain_mensuel
    """
    if "H1" not in scenarios or "H3" not in scenarios:
        return {
            "applicable": False,
            "explication": "Scénarios H1 et H3 requis pour le calcul."
        }

    sc_h1 = scenarios["H1"]
    sc_h3 = scenarios["H3"]

    date_h1 = parse_date(sc_h1["date_depart"])
    date_h3 = parse_date(sc_h3["date_depart"])

    pension_h1_net = sc_h1["pension_totale_nette_mensuelle"]
    pension_h3_net = sc_h3["pension_totale_nette_mensuelle"]

    # Cas : H3 démarre avant ou en même temps que H1 et pension supérieure
    if date_h3 <= date_h1 and pension_h3_net >= pension_h1_net:
        return {
            "applicable": True,
            "immediat": True,
            "mois": 0,
            "date": sc_h3["date_depart"],
            "explication": (
                "H3 offre une pension supérieure à H1 dès le départ : "
                "la stratégie optimale est immédiatement avantageuse."
            )
        }

    # Capital perdu : mensualités H1 pendant la période où H3 n'est pas encore parti
    rd_h1_h3 = relativedelta(date_h3, date_h1)
    mois_entre_h1_h3 = rd_h1_h3.years * 12 + rd_h1_h3.months
    mois_entre_h1_h3 = max(0, mois_entre_h1_h3)

    capital_perdu = round(pension_h1_net * mois_entre_h1_h3, 2)
    gain_mensuel = round(pension_h3_net - pension_h1_net, 2)

    if gain_mensuel <= 0:
        return {
            "applicable": True,
            "immediat": False,
            "mois": None,
            "date": None,
            "explication": "H3 n'offre pas de gain mensuel supérieur à H1.",
            "capital_perdu": capital_perdu,
            "gain_mensuel": gain_mensuel,
        }

    be_mois = ceil(capital_perdu / gain_mensuel)
    date_h3_obj = parse_date(sc_h3["date_depart"])
    be_date = date_h3_obj + relativedelta(months=be_mois)
    be_date_str = be_date.strftime('%Y-%m-%d')

    return {
        "applicable": True,
        "immediat": False,
        "mois": be_mois,
        "date": be_date_str,
        "capital_perdu": capital_perdu,
        "gain_mensuel": gain_mensuel,
        "explication": (
            f"H3 récupère l'avance de H1 ({capital_perdu:.2f}€) en {be_mois} mois "
            f"grâce à un gain mensuel de {gain_mensuel:.2f}€."
        )
    }


# ============================================================================
# IMPACT INFLATION
# ============================================================================

def calcul_inflation_impact(pension_nette: float, inflation: float) -> Dict:
    """
    Calcule la valeur réelle de la pension à 5, 10 et 20 ans.

    Returns:
        Dict : {valeur_reelle_5ans, valeur_reelle_10ans, valeur_reelle_20ans}
    """
    return {
        "valeur_reelle_5ans": round(pension_nette / ((1 + inflation) ** 5), 2),
        "valeur_reelle_10ans": round(pension_nette / ((1 + inflation) ** 10), 2),
        "valeur_reelle_20ans": round(pension_nette / ((1 + inflation) ** 20), 2),
    }


# ============================================================================
# API HANDLER - POINT D'ENTRÉE N8N
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour N8N (Code node Python mode).

    Params requis :
        date_naissance           : str  "DD/MM/YYYY" ou "YYYY-MM-DD"
        sam                      : float  Salaire Annuel Moyen
        trimestres_cotises_rg    : int   Trimestres cotisés Régime Général
        trimestres_valides_tous_regimes : int  Trimestres tous régimes confondus
        scenarios                : Dict  {"H1": {"date_depart": ...}, "H2": ..., "H3": ...}

    Params optionnels :
        date_reference           : str  (défaut : aujourd'hui)
        trimestres_etranger      : int  (défaut : 0)
        agirc_points             : float (défaut : 0.0)
        agirc_valeur_point       : float (défaut : VALEUR_POINT_AGIRC_2025)
        ircantec_points          : float (défaut : 0.0)
        ircantec_valeur_point    : float (défaut : VALEUR_POINT_IRCANTEC_2025)
        rci_points               : float (défaut : 0.0)
        rci_valeur_point         : float (défaut : VALEUR_POINT_RCI_2025)
        revenu_souhaite          : float (défaut : None)
        inflation                : float (défaut : 0.02)

    Returns:
        Dict : résultat complet de la simulation
    """
    try:
        # ----------------------------------------------------------------
        # 1. EXTRACTION ET VALIDATION DES PARAMÈTRES
        # ----------------------------------------------------------------
        date_naissance = parse_date(params['date_naissance'])
        sam_annuel = float(params['sam'])
        trimestres_cotises_rg = int(params['trimestres_cotises_rg'])
        trimestres_valides_tous_regimes = int(params['trimestres_valides_tous_regimes'])
        scenarios_params = params['scenarios']

        # Paramètres optionnels
        date_reference_str = params.get('date_reference', None)
        if date_reference_str:
            date_reference = parse_date(date_reference_str)
        else:
            date_reference = date.today()

        trimestres_etranger = int(params.get('trimestres_etranger', 0))

        agirc_points = float(params.get('agirc_points', 0.0))
        agirc_valeur_point = float(params.get('agirc_valeur_point', VALEUR_POINT_AGIRC_2025))
        ircantec_points = float(params.get('ircantec_points', 0.0))
        ircantec_valeur_point = float(params.get('ircantec_valeur_point', VALEUR_POINT_IRCANTEC_2025))
        rci_points = float(params.get('rci_points', 0.0))
        rci_valeur_point = float(params.get('rci_valeur_point', VALEUR_POINT_RCI_2025))

        revenu_souhaite = params.get('revenu_souhaite', None)
        if revenu_souhaite is not None:
            revenu_souhaite = float(revenu_souhaite)

        inflation = float(params.get('inflation', 0.02))

        # ----------------------------------------------------------------
        # 2. TOTALISATION : trimestres étrangers comptent pour le taux plein
        #    mais PAS pour la proratisation CNAV (trim_rg reste séparé)
        # ----------------------------------------------------------------
        trim_pour_tp = trimestres_valides_tous_regimes + trimestres_etranger
        trim_rg = trimestres_cotises_rg  # Proratisation CNAV : RG uniquement

        # ----------------------------------------------------------------
        # 3. DURÉE REQUISE & DATE TAUX PLEIN
        # ----------------------------------------------------------------
        duree_requise = get_duree_requise(date_naissance.year)

        date_taux_plein = calcul_date_taux_plein(
            date_naissance,
            trim_pour_tp,
            date_reference
        )

        # ----------------------------------------------------------------
        # 4. CALCUL DES SCÉNARIOS
        # ----------------------------------------------------------------
        scenarios_resultats = {}

        for code, sc_params in scenarios_params.items():
            is_progressive = sc_params.get('is_progressive', False)

            sc_result = calcul_scenario(
                code=code,
                date_depart_str=sc_params['date_depart'],
                date_naissance=date_naissance,
                date_reference=date_reference,
                date_taux_plein=date_taux_plein,
                sam_annuel=sam_annuel,
                trimestres_actuels_rg=trim_rg,
                trimestres_actuels_tous_regimes=trim_pour_tp,
                duree_requise=duree_requise,
                agirc_points=agirc_points,
                agirc_valeur_point=agirc_valeur_point,
                ircantec_points=ircantec_points,
                ircantec_valeur_point=ircantec_valeur_point,
                rci_points=rci_points,
                rci_valeur_point=rci_valeur_point,
                is_progressive=is_progressive,
            )
            scenarios_resultats[code] = sc_result

        # ----------------------------------------------------------------
        # 5. PROJECTION 20 ANS
        # ----------------------------------------------------------------
        projection_20ans = calcul_projection_20ans(
            scenarios=scenarios_resultats,
            date_naissance=date_naissance,
            date_reference=date_reference,
            agirc_points=agirc_points,
            agirc_valeur_point=agirc_valeur_point,
            inflation=inflation,
        )

        # ----------------------------------------------------------------
        # 6. BREAKEVEN H1 vs H3
        # ----------------------------------------------------------------
        breakeven = calcul_breakeven(scenarios_resultats, date_reference)

        # ----------------------------------------------------------------
        # 7. COMPARATIF H1 vs H3
        # ----------------------------------------------------------------
        comparatif = {}
        if "H1" in scenarios_resultats and "H3" in scenarios_resultats:
            ecart_mensuel = round(
                scenarios_resultats["H3"]["pension_totale_nette_mensuelle"] -
                scenarios_resultats["H1"]["pension_totale_nette_mensuelle"],
                2
            )
            comparatif = {
                "ecart_H3_H1_mensuel": ecart_mensuel,
                "ecart_H3_H1_annuel": round(ecart_mensuel * 12, 2),
            }

        # ----------------------------------------------------------------
        # 8. IMPACT INFLATION (sur la pension H3 ou H1 si H3 absent)
        # ----------------------------------------------------------------
        pension_ref_inflation = None
        if "H3" in scenarios_resultats:
            pension_ref_inflation = scenarios_resultats["H3"]["pension_totale_nette_mensuelle"]
        elif "H1" in scenarios_resultats:
            pension_ref_inflation = scenarios_resultats["H1"]["pension_totale_nette_mensuelle"]

        inflation_impact = {}
        if pension_ref_inflation is not None:
            inflation_impact = calcul_inflation_impact(pension_ref_inflation, inflation)

        # ----------------------------------------------------------------
        # 9. DÉFICIT PAR RAPPORT AU REVENU SOUHAITÉ
        # ----------------------------------------------------------------
        deficit = {}
        if revenu_souhaite is not None:
            pension_h3_nette = None
            if "H3" in scenarios_resultats:
                pension_h3_nette = scenarios_resultats["H3"]["pension_totale_nette_mensuelle"]
            elif "H1" in scenarios_resultats:
                pension_h3_nette = scenarios_resultats["H1"]["pension_totale_nette_mensuelle"]

            if pension_h3_nette is not None:
                deficit_mensuel = round(revenu_souhaite - pension_h3_nette, 2)
                # Capital nécessaire pour couvrir le déficit pendant 20 ans (valeur actualisée)
                # Utilise la formule de valeur actuelle d'une rente (annuity PV)
                if deficit_mensuel > 0:
                    monthly_rate = inflation / 12
                    n = 240  # 20 ans * 12 mois
                    if monthly_rate > 0:
                        capital_necessaire_20ans = round(
                            deficit_mensuel * (1 - (1 + monthly_rate) ** -n) / monthly_rate,
                            2
                        )
                    else:
                        capital_necessaire_20ans = round(deficit_mensuel * n, 2)
                else:
                    capital_necessaire_20ans = 0.0

                deficit = {
                    "revenu_souhaite": revenu_souhaite,
                    "pension_H3_nette": pension_h3_nette,
                    "deficit_mensuel": deficit_mensuel,
                    "capital_necessaire_20ans": capital_necessaire_20ans,
                }

        # ----------------------------------------------------------------
        # 10. INFORMATION TRIMESTRES ÉTRANGERS
        # ----------------------------------------------------------------
        etranger = {
            "detected": trimestres_etranger > 0,
            "trimestres_etranger": trimestres_etranger,
            "impact": (
                f"{trimestres_etranger} trimestre(s) étranger(s) pris en compte "
                f"pour le taux plein (totalisation UE/bilatérale). "
                f"Non inclus dans la proratisation CNAV."
            ) if trimestres_etranger > 0 else "Aucun trimestre étranger déclaré."
        }

        # ----------------------------------------------------------------
        # 11. CONSTRUCTION DU RÉSULTAT FINAL
        # ----------------------------------------------------------------
        return {
            "scenarios": scenarios_resultats,
            "date_taux_plein": date_taux_plein.strftime('%Y-%m-%d'),
            "duree_requise": duree_requise,
            "comparatif": comparatif,
            "projection_20ans": projection_20ans,
            "breakeven": breakeven,
            "deficit": deficit,
            "inflation_impact": inflation_impact,
            "etranger": etranger,
            "source_calcul": "python-simulation-v1",
        }

    except Exception as e:
        return {
            "erreur": True,
            "message": f"Erreur lors du calcul : {str(e)}",
            "type": "erreur_calcul",
        }


# ============================================================================
# EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":

    print("=" * 80)
    print("EXEMPLE - SIMULATION RETRAITE MULTI-RÉGIMES")
    print("=" * 80)

    params = {
        "date_naissance": "15/03/1965",
        "sam": 32000.0,
        "trimestres_cotises_rg": 162,
        "trimestres_valides_tous_regimes": 168,
        "trimestres_etranger": 8,
        "agirc_points": 28450.0,
        "ircantec_points": 0.0,
        "rci_points": 0.0,
        "revenu_souhaite": 2800.0,
        "scenarios": {
            "H1": {"date_depart": "2026-04-15"},
            "H2": {"date_depart": "2027-09-01"},
            "H3": {"date_depart": "2028-09-01"},
        }
    }

    r = api_handler(params)
    print(json.dumps(r, indent=2, ensure_ascii=False))
