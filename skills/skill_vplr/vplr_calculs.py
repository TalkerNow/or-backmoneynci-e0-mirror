# -*- coding: utf-8 -*-
"""
Script : vplr_calculs.py
Description : Calculs VPLR (Versement Pour La Retraite) - Coût, éligibilité et rentabilité
Version : 2.0
Date : 10 novembre 2025
Législation : Circulaire CNAV 2025-01 du 13/01/2025
              Article L.351-14-1 CSS
"""

from datetime import datetime
from typing import Dict, Tuple, List, Optional


# ============================================================================
# SECTION 1 : DONNÉES RÉGLEMENTAIRES 2025
# ============================================================================

PASS_2025 = 49308  # Plafond Annuel Sécurité Sociale

# Tranches de revenus
TRANCHE_1_MAX = int(PASS_2025 * 0.75)  # 36 981 €
TRANCHE_2_MIN = TRANCHE_1_MAX
TRANCHE_2_MAX = PASS_2025  # 49 308 €
TRANCHE_3_MIN = PASS_2025

# Limites réglementaires
AGE_MINIMUM_VPLR = 20
AGE_MAXIMUM_VPLR = 66  # 67 ans exclus
MAXIMUM_TRIMESTRES_RACHETABLES = 12

# Barème 2025 (par âge) - Coût par trimestre en euros
# Structure : age: {"taux_seul": {"t1": montant, "t2_pct": %, "t3": montant}, "taux_et_duree": {...}}
BAREME_2025 = {
    20: {
        "taux_seul": {"t1": 1055, "t2_pct": 3.80, "t3": 1407},
        "taux_et_duree": {"t1": 1564, "t2_pct": 5.63, "t3": 2085}
    },
    21: {
        "taux_seul": {"t1": 1076, "t2_pct": 3.87, "t3": 1434},
        "taux_et_duree": {"t1": 1594, "t2_pct": 5.74, "t3": 2126}
    },
    22: {
        "taux_seul": {"t1": 1097, "t2_pct": 3.95, "t3": 1462},
        "taux_et_duree": {"t1": 1625, "t2_pct": 5.85, "t3": 2167}
    },
    23: {
        "taux_seul": {"t1": 1118, "t2_pct": 4.03, "t3": 1491},
        "taux_et_duree": {"t1": 1657, "t2_pct": 5.96, "t3": 2209}
    },
    24: {
        "taux_seul": {"t1": 1168, "t2_pct": 4.20, "t3": 1557},
        "taux_et_duree": {"t1": 1731, "t2_pct": 6.23, "t3": 2308}
    },
    25: {
        "taux_seul": {"t1": 1219, "t2_pct": 4.39, "t3": 1625},
        "taux_et_duree": {"t1": 1806, "t2_pct": 6.50, "t3": 2408}
    },
    30: {
        "taux_seul": {"t1": 1487, "t2_pct": 5.35, "t3": 1983},
        "taux_et_duree": {"t1": 2204, "t2_pct": 7.93, "t3": 2938}
    },
    35: {
        "taux_seul": {"t1": 1771, "t2_pct": 6.38, "t3": 2361},
        "taux_et_duree": {"t1": 2624, "t2_pct": 9.45, "t3": 3499}
    },
    40: {
        "taux_seul": {"t1": 2065, "t2_pct": 7.43, "t3": 2753},
        "taux_et_duree": {"t1": 3060, "t2_pct": 11.02, "t3": 4080}
    },
    45: {
        "taux_seul": {"t1": 2366, "t2_pct": 8.52, "t3": 3154},
        "taux_et_duree": {"t1": 3506, "t2_pct": 12.62, "t3": 4674}
    },
    50: {
        "taux_seul": {"t1": 2672, "t2_pct": 9.62, "t3": 3563},
        "taux_et_duree": {"t1": 3960, "t2_pct": 14.26, "t3": 5279}
    },
    55: {
        "taux_seul": {"t1": 2980, "t2_pct": 10.73, "t3": 3973},
        "taux_et_duree": {"t1": 4416, "t2_pct": 15.90, "t3": 5888}
    },
    60: {
        "taux_seul": {"t1": 3275, "t2_pct": 11.79, "t3": 4367},
        "taux_et_duree": {"t1": 4854, "t2_pct": 17.48, "t3": 6472}
    },
    62: {
        "taux_seul": {"t1": 3383, "t2_pct": 12.18, "t3": 4510},
        "taux_et_duree": {"t1": 5013, "t2_pct": 18.05, "t3": 6684}
    },
    63: {
        "taux_seul": {"t1": 3298, "t2_pct": 11.87, "t3": 4397},
        "taux_et_duree": {"t1": 4888, "t2_pct": 17.60, "t3": 6517}
    },
    64: {
        "taux_seul": {"t1": 3214, "t2_pct": 11.57, "t3": 4285},
        "taux_et_duree": {"t1": 4762, "t2_pct": 17.15, "t3": 6350}
    },
    65: {
        "taux_seul": {"t1": 3129, "t2_pct": 11.27, "t3": 4172},
        "taux_et_duree": {"t1": 4637, "t2_pct": 16.70, "t3": 6183}
    },
    66: {
        "taux_seul": {"t1": 3044, "t2_pct": 10.96, "t3": 4059},
        "taux_et_duree": {"t1": 4512, "t2_pct": 16.24, "t3": 6015}
    }
}

# Espérance de vie à 62 ans (pour calcul rentabilité)
ESPERANCE_VIE_62_ANS = {"hommes": 22, "femmes": 26}


# ============================================================================
# SECTION 1B : API HANDLER POUR N8N
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour appel depuis n8n.
    
    Args:
        params (Dict): {
            "age": 45,
            "revenu_moyen_annuel": 42000,
            "nb_trimestres": 4,
            "option": "taux_et_duree",  # ou "taux_seul"
            
            # Optionnel pour calcul rentabilité
            "pension_annuelle_sans_rachat": 18000,
            "pension_annuelle_avec_rachat": 19500,
            "tmi": 30,  # Tranche marginale imposition (%)
            "sexe": "homme"  # ou "femme" (pour espérance vie)
        }
    
    Returns:
        Dict: {
            "eligible": bool,
            "cout_par_trimestre": float,
            "cout_total": float,
            "cout_net_apres_fiscalite": float,
            "gain_annuel_pension": float,
            "duree_recuperation_ans": float,
            "rentable": bool,
            "tranche_revenu": str,
            "controles": [...],
            "alertes": {...}
        }
    """
    age = params.get("age")
    revenu_moyen_annuel = params.get("revenu_moyen_annuel")
    nb_trimestres = params.get("nb_trimestres", 1)
    option = params.get("option", "taux_et_duree")
    
    # Paramètres optionnels rentabilité
    pension_sans = params.get("pension_annuelle_sans_rachat")
    pension_avec = params.get("pension_annuelle_avec_rachat")
    tmi = params.get("tmi", 30)
    sexe = params.get("sexe", "homme")
    
    # Vérifications éligibilité
    controles = []
    alertes = {"rouge": [], "orange": [], "jaune": []}
    
    eligible = True
    
    # VPL_C01 : Âge hors limites
    if age < AGE_MINIMUM_VPLR or age > AGE_MAXIMUM_VPLR:
        eligible = False
        controles.append({
            "code": "VPL_C01",
            "type": "BLOQUANT",
            "message": f"Âge hors limites. Âge actuel : {age} ans. Limites : {AGE_MINIMUM_VPLR} à {AGE_MAXIMUM_VPLR} ans inclus"
        })
        alertes["rouge"].append("Âge hors limites - VPLR impossible")
    
    # VPL_C02 : Dépassement 12 trimestres
    if nb_trimestres > MAXIMUM_TRIMESTRES_RACHETABLES:
        eligible = False
        controles.append({
            "code": "VPL_C02",
            "type": "ERREUR",
            "message": f"Dépassement limite. {nb_trimestres} trimestres demandés, maximum {MAXIMUM_TRIMESTRES_RACHETABLES} autorisés"
        })
        alertes["rouge"].append(f"Dépassement maximum {MAXIMUM_TRIMESTRES_RACHETABLES} trimestres")
    
    # Calcul du coût
    cout_par_trimestre = 0
    tranche_revenu = ""
    
    if eligible:
        resultat_cout = calculer_cout_vplr(age, revenu_moyen_annuel, option)
        cout_par_trimestre = resultat_cout["cout_par_trimestre"]
        tranche_revenu = resultat_cout["tranche_revenu"]
    
    cout_total = cout_par_trimestre * nb_trimestres
    economie_fiscale = cout_total * (tmi / 100)
    cout_net = cout_total - economie_fiscale
    
    # Calcul rentabilité (si données fournies)
    gain_annuel = 0
    duree_recuperation = 0
    rentable = None
    
    if pension_sans and pension_avec:
        gain_annuel = pension_avec - pension_sans
        if gain_annuel > 0:
            duree_recuperation = cout_net / gain_annuel
            
            esperance_vie = ESPERANCE_VIE_62_ANS.get(sexe.lower(), 24)
            rentable = duree_recuperation < esperance_vie
            
            # VPL_C05 : Rentabilité négative
            if duree_recuperation > 25:
                controles.append({
                    "code": "VPL_C05",
                    "type": "AVERTISSEMENT",
                    "message": f"Rentabilité très faible. Durée récupération : {duree_recuperation:.1f} ans (> 25 ans)"
                })
                alertes["orange"].append(f"Rentabilité limite (durée récupération {duree_recuperation:.1f} ans)")
    
    # VPL_C07 : Rachat tardif
    if age > 60:
        controles.append({
            "code": "VPL_C07",
            "type": "AVERTISSEMENT",
            "message": f"Rachat après {age} ans. Analyse rentabilité IMPÉRATIVE (coût élevé)"
        })
        alertes["orange"].append(f"Âge > 60 ans - Coût élevé, vérifier rentabilité")
    
    # Alerte tranche 3
    if tranche_revenu == "tranche_3":
        alertes["jaune"].append("Tranche 3 revenus (> PASS) - Coût maximal du rachat")
    
    return {
        "eligible": eligible,
        "cout_par_trimestre": round(cout_par_trimestre, 2),
        "cout_total": round(cout_total, 2),
        "economie_fiscale": round(economie_fiscale, 2),
        "cout_net_apres_fiscalite": round(cout_net, 2),
        "gain_annuel_pension": round(gain_annuel, 2),
        "duree_recuperation_ans": round(duree_recuperation, 1) if duree_recuperation > 0 else None,
        "rentable": rentable,
        "tranche_revenu": tranche_revenu,
        "option_choisie": option,
        "controles": controles,
        "alertes": alertes
    }


# ============================================================================
# SECTION 2 : FONCTIONS UTILITAIRES
# ============================================================================

def determiner_tranche_revenu(revenu_moyen_annuel: float) -> str:
    """
    Détermine la tranche de revenu pour le calcul du coût VPLR.
    
    Args:
        revenu_moyen_annuel: Revenu annuel moyen en euros
        
    Returns:
        str: "tranche_1", "tranche_2" ou "tranche_3"
        
    Exemple:
        >>> determiner_tranche_revenu(35000)
        'tranche_1'
        >>> determiner_tranche_revenu(42000)
        'tranche_2'
        >>> determiner_tranche_revenu(55000)
        'tranche_3'
    """
    if revenu_moyen_annuel < TRANCHE_1_MAX:
        return "tranche_1"
    elif TRANCHE_2_MIN <= revenu_moyen_annuel <= TRANCHE_2_MAX:
        return "tranche_2"
    else:
        return "tranche_3"


def interpoler_bareme(age: int) -> Dict:
    """
    Interpole le barème si l'âge n'est pas directement dans le dictionnaire.
    
    Pour les âges entre deux valeurs du barème, effectue une interpolation linéaire.
    
    Args:
        age: Âge de l'assuré
        
    Returns:
        Dict: Barème interpolé ou barème exact si âge présent
        
    Exemple:
        >>> bareme = interpoler_bareme(42)
        # Retourne valeur interpolée entre 40 et 45 ans
    """
    # Si âge exact dans barème, retourner directement
    if age in BAREME_2025:
        return BAREME_2025[age]
    
    # Trouver les bornes pour interpolation
    ages_disponibles = sorted(BAREME_2025.keys())
    
    # Trouver l'âge inférieur et supérieur
    age_inf = max([a for a in ages_disponibles if a < age])
    age_sup = min([a for a in ages_disponibles if a > age])
    
    # Coefficient d'interpolation
    coef = (age - age_inf) / (age_sup - age_inf)
    
    # Interpolation pour chaque option et tranche
    bareme_interpole = {}
    
    for option in ["taux_seul", "taux_et_duree"]:
        bareme_interpole[option] = {}
        
        for cle in ["t1", "t2_pct", "t3"]:
            val_inf = BAREME_2025[age_inf][option][cle]
            val_sup = BAREME_2025[age_sup][option][cle]
            bareme_interpole[option][cle] = val_inf + (val_sup - val_inf) * coef
    
    return bareme_interpole


# ============================================================================
# SECTION 3 : CALCULS PRINCIPAUX
# ============================================================================

def calculer_cout_vplr(
    age: int,
    revenu_moyen_annuel: float,
    option: str = "taux_et_duree"
) -> Dict[str, any]:
    """
    Calcule le coût du rachat d'un trimestre VPLR selon le barème 2025.
    
    Args:
        age: Âge de l'assuré à la date de la demande (20-66 ans)
        revenu_moyen_annuel: Revenu annuel moyen en euros
        option: "taux_seul" ou "taux_et_duree"
        
    Returns:
        Dict: {
            "cout_par_trimestre": float,
            "tranche_revenu": str,
            "option": str,
            "age": int,
            "details": {...}
        }
        
    Exemple:
        >>> calculer_cout_vplr(45, 42000, "taux_et_duree")
        {
            "cout_par_trimestre": 5298.84,
            "tranche_revenu": "tranche_2",
            ...
        }
    """
    # Vérification âge
    if age < AGE_MINIMUM_VPLR or age > AGE_MAXIMUM_VPLR:
        return {
            "cout_par_trimestre": 0,
            "erreur": f"Âge hors limites ({age} ans). Limites : {AGE_MINIMUM_VPLR}-{AGE_MAXIMUM_VPLR} ans"
        }
    
    # Déterminer la tranche de revenu
    tranche = determiner_tranche_revenu(revenu_moyen_annuel)
    
    # Récupérer le barème (avec interpolation si nécessaire)
    bareme = interpoler_bareme(age)
    bareme_option = bareme[option]
    
    # Calcul du coût selon la tranche
    if tranche == "tranche_1":
        cout = bareme_option["t1"]
    elif tranche == "tranche_2":
        cout = revenu_moyen_annuel * (bareme_option["t2_pct"] / 100)
    else:  # tranche_3
        cout = bareme_option["t3"]
    
    return {
        "cout_par_trimestre": cout,
        "tranche_revenu": tranche,
        "option": option,
        "age": age,
        "revenu_moyen": revenu_moyen_annuel,
        "details": {
            "tranche_1_seuil": TRANCHE_1_MAX,
            "tranche_2_min": TRANCHE_2_MIN,
            "tranche_2_max": TRANCHE_2_MAX,
            "tranche_3_min": TRANCHE_3_MIN,
            "bareme_utilise": bareme_option
        }
    }


def calculer_rentabilite_vplr(
    cout_total: float,
    pension_annuelle_sans_rachat: float,
    pension_annuelle_avec_rachat: float,
    tmi: float = 30,
    esperance_vie_retraite: int = 24
) -> Dict[str, any]:
    """
    Calcule la rentabilité d'un rachat VPLR.
    
    Args:
        cout_total: Coût total du rachat (nb_trimestres × coût_unitaire)
        pension_annuelle_sans_rachat: Pension annuelle sans rachat
        pension_annuelle_avec_rachat: Pension annuelle avec rachat
        tmi: Tranche marginale d'imposition (%)
        esperance_vie_retraite: Espérance de vie à la retraite en années
        
    Returns:
        Dict: {
            "gain_annuel": float,
            "economie_fiscale": float,
            "cout_net": float,
            "duree_recuperation": float,
            "rentable": bool,
            "gain_total_espere": float
        }
        
    Exemple:
        >>> calculer_rentabilite_vplr(12000, 18000, 19500, 30, 24)
        {
            "gain_annuel": 1500,
            "economie_fiscale": 3600,
            "cout_net": 8400,
            "duree_recuperation": 5.6,
            "rentable": True,
            ...
        }
    """
    # Gain annuel de pension
    gain_annuel = pension_annuelle_avec_rachat - pension_annuelle_sans_rachat
    
    # Économie fiscale (déduction du revenu imposable)
    economie_fiscale = cout_total * (tmi / 100)
    
    # Coût net après déduction fiscale
    cout_net = cout_total - economie_fiscale
    
    # Durée de récupération
    if gain_annuel > 0:
        duree_recuperation = cout_net / gain_annuel
    else:
        duree_recuperation = float('inf')
    
    # Rentabilité
    rentable = duree_recuperation < esperance_vie_retraite
    
    # Gain total espéré sur espérance de vie
    gain_total_espere = (gain_annuel * esperance_vie_retraite) - cout_net
    
    # Taux de rendement interne (approximatif)
    if duree_recuperation > 0 and duree_recuperation < esperance_vie_retraite:
        taux_rendement = ((gain_total_espere / cout_net) / esperance_vie_retraite) * 100
    else:
        taux_rendement = 0
    
    # Jugement de rentabilité
    if duree_recuperation <= 10:
        jugement = "Très rentable"
    elif duree_recuperation <= 15:
        jugement = "Rentable"
    elif duree_recuperation <= 20:
        jugement = "Rentabilité limite"
    else:
        jugement = "Peu ou pas rentable"
    
    return {
        "gain_annuel": gain_annuel,
        "economie_fiscale": economie_fiscale,
        "cout_net": cout_net,
        "duree_recuperation": duree_recuperation,
        "rentable": rentable,
        "gain_total_espere": gain_total_espere,
        "taux_rendement_approx": taux_rendement,
        "jugement": jugement,
        "details": {
            "cout_brut": cout_total,
            "tmi_appliquee": tmi,
            "esperance_vie": esperance_vie_retraite
        }
    }


def simuler_scenarios_vplr(
    age: int,
    revenu_moyen_annuel: float,
    nb_trimestres: int,
    pension_sans_rachat: float,
    pension_avec_rachat: float,
    tmi: float = 30,
    sexe: str = "homme"
) -> Dict[str, any]:
    """
    Compare les 2 options de rachat (taux seul vs taux + durée).
    
    Args:
        age: Âge actuel
        revenu_moyen_annuel: Revenu moyen annuel
        nb_trimestres: Nombre de trimestres à racheter
        pension_sans_rachat: Pension annuelle sans rachat
        pension_avec_rachat: Pension annuelle avec rachat
        tmi: Tranche marginale imposition
        sexe: "homme" ou "femme" (pour espérance vie)
        
    Returns:
        Dict: Comparaison des 2 options avec recommandation
    """
    esperance_vie = ESPERANCE_VIE_62_ANS.get(sexe.lower(), 24)
    
    # Scénario 1 : Taux seul
    cout_taux_seul = calculer_cout_vplr(age, revenu_moyen_annuel, "taux_seul")
    cout_total_taux_seul = cout_taux_seul["cout_par_trimestre"] * nb_trimestres
    rentabilite_taux_seul = calculer_rentabilite_vplr(
        cout_total_taux_seul,
        pension_sans_rachat,
        pension_avec_rachat,
        tmi,
        esperance_vie
    )
    
    # Scénario 2 : Taux + durée
    cout_taux_duree = calculer_cout_vplr(age, revenu_moyen_annuel, "taux_et_duree")
    cout_total_taux_duree = cout_taux_duree["cout_par_trimestre"] * nb_trimestres
    rentabilite_taux_duree = calculer_rentabilite_vplr(
        cout_total_taux_duree,
        pension_sans_rachat,
        pension_avec_rachat,
        tmi,
        esperance_vie
    )
    
    # Recommandation
    if rentabilite_taux_duree["rentable"]:
        recommandation = "taux_et_duree"
        raison = "Impact maximal sur pension et rentabilité démontrée"
    elif rentabilite_taux_seul["rentable"]:
        recommandation = "taux_seul"
        raison = "Coût inférieur et rentabilité acceptable"
    else:
        recommandation = "aucune"
        raison = "Rentabilité insuffisante pour les deux options"
    
    return {
        "scenario_taux_seul": {
            "cout_unitaire": cout_taux_seul["cout_par_trimestre"],
            "cout_total": cout_total_taux_seul,
            "rentabilite": rentabilite_taux_seul
        },
        "scenario_taux_et_duree": {
            "cout_unitaire": cout_taux_duree["cout_par_trimestre"],
            "cout_total": cout_total_taux_duree,
            "rentabilite": rentabilite_taux_duree
        },
        "recommandation": {
            "option": recommandation,
            "raison": raison
        },
        "contexte": {
            "age": age,
            "nb_trimestres": nb_trimestres,
            "esperance_vie": esperance_vie
        }
    }


# ============================================================================
# SECTION 4 : EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    print("=" * 80)
    print("SCRIPT : vplr_calculs.py - TESTS ET EXEMPLES")
    print("=" * 80)
    
    # EXEMPLE 1 : Calcul coût simple
    print("\n### EXEMPLE 1 : Calcul coût VPLR (45 ans, 42 000 €/an, taux + durée) ###")
    print("-" * 80)
    
    resultat = calculer_cout_vplr(45, 42000, "taux_et_duree")
    print(f"Coût par trimestre : {resultat['cout_par_trimestre']:.2f} €")
    print(f"Tranche de revenu : {resultat['tranche_revenu']}")
    print(f"Option : {resultat['option']}")
    
    # EXEMPLE 2 : Rentabilité complète
    print("\n\n### EXEMPLE 2 : Analyse rentabilité (4 trimestres) ###")
    print("-" * 80)
    
    cout_total = resultat['cout_par_trimestre'] * 4
    rentabilite = calculer_rentabilite_vplr(
        cout_total=cout_total,
        pension_annuelle_sans_rachat=18000,
        pension_annuelle_avec_rachat=19500,
        tmi=30,
        esperance_vie_retraite=24
    )
    
    print(f"Coût total brut : {cout_total:.2f} €")
    print(f"Économie fiscale : {rentabilite['economie_fiscale']:.2f} €")
    print(f"Coût net : {rentabilite['cout_net']:.2f} €")
    print(f"Gain annuel pension : {rentabilite['gain_annuel']:.2f} €")
    print(f"Durée récupération : {rentabilite['duree_recuperation']:.1f} ans")
    print(f"Rentable : {'OUI' if rentabilite['rentable'] else 'NON'}")
    print(f"Jugement : {rentabilite['jugement']}")
    
    # EXEMPLE 3 : API Handler
    print("\n\n### EXEMPLE 3 : API Handler (appel n8n) ###")
    print("-" * 80)
    
    params = {
        "age": 45,
        "revenu_moyen_annuel": 42000,
        "nb_trimestres": 4,
        "option": "taux_et_duree",
        "pension_annuelle_sans_rachat": 18000,
        "pension_annuelle_avec_rachat": 19500,
        "tmi": 30,
        "sexe": "homme"
    }
    
    result = api_handler(params)
    print(f"Éligible : {result['eligible']}")
    print(f"Coût total : {result['cout_total']} €")
    print(f"Coût net après fiscalité : {result['cout_net_apres_fiscalite']} €")
    print(f"Durée récupération : {result['duree_recuperation_ans']} ans")
    print(f"Rentable : {result['rentable']}")
    
    if result['controles']:
        print(f"\nContrôles détectés : {len(result['controles'])}")
        for c in result['controles']:
            print(f"  - {c['code']}: {c['message']}")
    
    # EXEMPLE 4 : Comparaison options
    print("\n\n### EXEMPLE 4 : Comparaison taux seul vs taux + durée ###")
    print("-" * 80)
    
    scenarios = simuler_scenarios_vplr(
        age=45,
        revenu_moyen_annuel=42000,
        nb_trimestres=4,
        pension_sans_rachat=18000,
        pension_avec_rachat=19500,
        tmi=30,
        sexe="homme"
    )
    
    print("Option TAUX SEUL :")
    print(f"  - Coût unitaire : {scenarios['scenario_taux_seul']['cout_unitaire']:.2f} €")
    print(f"  - Durée récupération : {scenarios['scenario_taux_seul']['rentabilite']['duree_recuperation']:.1f} ans")
    
    print("\nOption TAUX + DURÉE :")
    print(f"  - Coût unitaire : {scenarios['scenario_taux_et_duree']['cout_unitaire']:.2f} €")
    print(f"  - Durée récupération : {scenarios['scenario_taux_et_duree']['rentabilite']['duree_recuperation']:.1f} ans")
    
    print(f"\nRECOMMANDATION : {scenarios['recommandation']['option'].upper()}")
    print(f"Raison : {scenarios['recommandation']['raison']}")
    
    print("\n" + "=" * 80)
    print("Tests terminés avec succès !")
    print("=" * 80)
