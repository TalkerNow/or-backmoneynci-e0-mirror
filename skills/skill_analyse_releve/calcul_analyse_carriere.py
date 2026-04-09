# -*- coding: utf-8 -*-
"""
Script : calcul_analyse_carriere.py
Description : Analyse complète d'un relevé de carrière retraite français
Version : 1.0
Date : Novembre 2025
Législation : Circulaire CNAV 2024, Loi n°2023-270 du 14 avril 2023

Ce script automatise :
- Extraction des données du relevé de carrière
- Calcul des âges clés (légal, taux plein, 67 ans)
- Projection des trimestres futurs si activité en cours
- Analyse de la situation retraite (taux plein, décote, surcote)
- Estimation des pensions complémentaires (AGIRC-ARRCO, IRCANTEC, RCI)
- Détection automatique de l'éligibilité carrière longue

Format de dates : JJ/MM/AAAA (exemple : 15/03/1965)
"""

from datetime import datetime
from typing import Dict, List, Tuple, Optional


# ============================================================================
# SECTION 1 : DONNÉES RÉGLEMENTAIRES
# ============================================================================

# Valeurs des points 2025
VALEUR_POINT_AGIRC_ARRCO = 1.4386  # Euros
VALEUR_POINT_IRCANTEC = 0.56357    # Euros
VALEUR_POINT_RCO = 1.200           # Euros
VALEUR_POINT_RCI = 1.335           # Euros

# Prélèvements sociaux (%)
CSG_TAUX_NORMAL = 8.3
CSG_TAUX_MEDIAN = 6.6
CSG_TAUX_REDUIT = 3.8
CRDS = 0.5
CONTRIBUTION_SOLIDARITE = 0.3

# Taux de liquidation
TAUX_PLEIN = 50.0
TAUX_MINIMAL = 37.5
AGE_TAUX_PLEIN_AUTO = 67

# Décote et surcote
DECOTE_PAR_TRIMESTRE = 0.625  # 0,625% par trimestre
SURCOTE_PAR_TRIMESTRE = 1.25  # 1,25% par trimestre


# ============================================================================
# SECTION 1B : API HANDLER POUR N8N (Point d'entrée unifié)
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour appel depuis n8n.
    
    Cette fonction standardise l'interface d'appel pour les workflows n8n.
    Elle prend un dictionnaire de paramètres et retourne un résultat structuré
    incluant l'analyse complète du relevé de carrière.
    
    Args:
        params (Dict): Dictionnaire contenant les paramètres d'entrée
            {
                "date_naissance": "15/03/1965",  # Format JJ/MM/AAAA
                "trimestres_valides_actuels": 165,
                "en_activite": true,  # Boolean
                "age_depart_prevu": 64,  # Si en activité
                
                # Régimes (optionnels)
                "points_agirc_arrco": 5432,
                "points_ircantec_a": 1234,
                "points_ircantec_b": 567,
                "points_rco": 234,
                "points_rci": 456,
                
                # Périodes particulières (optionnels)
                "trimestres_avant_16_ans": 0,
                "trimestres_avant_18_ans": 0,
                "trimestres_avant_20_ans": 5,
                "trimestres_avant_21_ans": 8,
                
                # Anomalies (optionnels)
                "lacunes_carriere": [
                    {"annee_debut": 2010, "annee_fin": 2011, "raison": "Non reporté"}
                ],
                "periodes_etranger": [
                    {"pays": "Allemagne", "annee_debut": 2005, "annee_fin": 2008}
                ]
            }
    
    Returns:
        Dict: Résultat structuré pour n8n
            {
                "profil": {
                    "date_naissance": "15/03/1965",
                    "generation": 1965,
                    "age_actuel": 60
                },
                "situation_actuelle": {
                    "trimestres_valides": 165,
                    "trimestres_projetes": 181,
                    "trimestres_requis": 172,
                    "trimestres_manquants": 7,
                    "en_activite": true,
                    "projection_age": 64
                },
                "ages_cles": {
                    "age_legal": {"ans": 63, "mois": 3, "date": "01/07/2028"},
                    "taux_plein_auto": {"ans": 67, "mois": 0, "date": "01/04/2032"},
                    "date_effet_legal": "01/07/2028"
                },
                "scenarios": {
                    "scenario_1_age_legal": {
                        "taux": 47.5,
                        "type": "decote",
                        "trimestres_manquants": 7,
                        "decote_pct": 4.375
                    },
                    "scenario_2_67_ans": {
                        "taux": 50.0,
                        "type": "taux_plein_auto"
                    },
                    "scenario_3_surcote": null
                },
                "pensions_complementaires": {
                    "agirc_arrco": {
                        "points": 5432,
                        "montant_annuel_brut": 7814.35,
                        "montant_mensuel_brut": 651.20
                    },
                    "ircantec": {
                        "points_a": 1234,
                        "points_b": 567,
                        "montant_annuel_brut": 1014.43,
                        "montant_mensuel_brut": 84.54
                    },
                    "rci": {
                        "points_rco": 234,
                        "points_rci": 456,
                        "montant_annuel_brut": 889.56,
                        "montant_mensuel_brut": 74.13
                    },
                    "total_brut_annuel": 9718.34,
                    "total_brut_mensuel": 809.87
                },
                "carriere_longue": {
                    "eligible": true,
                    "trimestres_avant_20_ans": 5,
                    "action": "Déclencher SKILL_racl.md pour analyse détaillée"
                },
                "rachat_trimestres": {
                    "trimestres_rachetables": 7,
                    "impact": "Départ au taux plein dès 01/07/2028",
                    "cout_estime_min": 28000,
                    "cout_estime_max": 42000
                },
                "controles": [
                    {
                        "code": "ARC_C04",
                        "type": "AVERTISSEMENT",
                        "message": "Plus de 4 trimestres validés dans l'année 2015"
                    }
                ],
                "alertes": {
                    "rouge": [],
                    "orange": [
                        "Proche du taux plein (7 trimestres manquants) - Envisager rachat VPLR"
                    ],
                    "jaune": [
                        "Projection d'activité nécessaire jusqu'à 64 ans"
                    ]
                }
            }
    
    Exemple d'utilisation dans n8n:
        >>> result = api_handler({
        ...     "date_naissance": "15/03/1965",
        ...     "trimestres_valides_actuels": 165,
        ...     "en_activite": true,
        ...     "age_depart_prevu": 64,
        ...     "points_agirc_arrco": 5432
        ... })
        >>> print(result["situation_actuelle"]["trimestres_projetes"])  # 181
        >>> print(result["carriere_longue"]["eligible"])  # true
    """
    # Extraire les paramètres principaux
    date_naissance = params.get("date_naissance")
    trimestres_valides_actuels = params.get("trimestres_valides_actuels", 0)
    en_activite = params.get("en_activite", False)
    age_depart_prevu = params.get("age_depart_prevu", None)
    
    # Extraire les points régimes complémentaires
    points_agirc_arrco = params.get("points_agirc_arrco", 0)
    points_ircantec_a = params.get("points_ircantec_a", 0)
    points_ircantec_b = params.get("points_ircantec_b", 0)
    points_rco = params.get("points_rco", 0)
    points_rci = params.get("points_rci", 0)
    
    # Extraire les trimestres avant certains âges (carrière longue)
    trimestres_avant_16_ans = params.get("trimestres_avant_16_ans", 0)
    trimestres_avant_18_ans = params.get("trimestres_avant_18_ans", 0)
    trimestres_avant_20_ans = params.get("trimestres_avant_20_ans", 0)
    trimestres_avant_21_ans = params.get("trimestres_avant_21_ans", 0)
    
    # Anomalies et périodes particulières
    lacunes_carriere = params.get("lacunes_carriere", [])
    periodes_etranger = params.get("periodes_etranger", [])
    
    # Validation date de naissance
    controles = []
    alertes = {"rouge": [], "orange": [], "jaune": []}
    
    if not date_naissance:
        controles.append({
            "code": "ARC_C01",
            "type": "ERREUR_CRITIQUE",
            "message": "Absence de date de naissance dans le relevé"
        })
        alertes["rouge"].append("Impossible d'analyser sans date de naissance")
        return {
            "erreur": "Date de naissance manquante",
            "controles": controles,
            "alertes": alertes
        }
    
    # Calculer le profil
    profil = calculer_profil(date_naissance)
    
    # Projection des trimestres si en activité
    situation_actuelle = calculer_situation_actuelle(
        trimestres_valides_actuels,
        en_activite,
        profil["age_actuel"],
        age_depart_prevu,
        profil["generation"]
    )
    
    # Calculer les âges clés
    ages_cles = calculer_ages_cles(date_naissance)
    
    # Calculer les scénarios de départ
    scenarios = calculer_scenarios_depart(
        date_naissance,
        situation_actuelle["trimestres_projetes"],
        situation_actuelle["trimestres_requis"],
        ages_cles
    )
    
    # Calculer les pensions complémentaires
    pensions_complementaires = calculer_pensions_complementaires(
        date_naissance,
        ages_cles["age_legal"]["date"],
        situation_actuelle["trimestres_projetes"],
        situation_actuelle["trimestres_requis"],
        points_agirc_arrco,
        points_ircantec_a,
        points_ircantec_b,
        points_rco,
        points_rci
    )
    
    # Vérifier éligibilité carrière longue
    carriere_longue = verifier_carriere_longue(
        trimestres_avant_16_ans,
        trimestres_avant_18_ans,
        trimestres_avant_20_ans,
        trimestres_avant_21_ans
    )
    
    # Calculer options de rachat
    rachat_trimestres = calculer_rachat_trimestres(
        situation_actuelle["trimestres_manquants"],
        profil["age_actuel"]
    )
    
    # Générer les contrôles de cohérence
    generer_controles_coherence(
        controles,
        trimestres_valides_actuels,
        profil["age_actuel"],
        lacunes_carriere,
        points_agirc_arrco + points_ircantec_a + points_ircantec_b + points_rco + points_rci
    )
    
    # Générer les alertes
    generer_alertes(
        alertes,
        situation_actuelle,
        lacunes_carriere,
        periodes_etranger,
        carriere_longue,
        en_activite
    )
    
    # Construction de la réponse complète
    return {
        "profil": profil,
        "situation_actuelle": situation_actuelle,
        "ages_cles": ages_cles,
        "scenarios": scenarios,
        "pensions_complementaires": pensions_complementaires,
        "carriere_longue": carriere_longue,
        "rachat_trimestres": rachat_trimestres,
        "controles": controles,
        "alertes": alertes,
        "timestamp": datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    }


# ============================================================================
# SECTION 2 : FONCTIONS DE CALCUL
# ============================================================================

def calculer_profil(date_naissance: str) -> Dict:
    """
    Calcule le profil de l'assuré (génération, âge actuel).
    
    Args:
        date_naissance: Date au format JJ/MM/AAAA
    
    Returns:
        Dict contenant génération et âge actuel
    """
    date_obj = datetime.strptime(date_naissance, "%d/%m/%Y")
    generation = date_obj.year
    
    aujourd_hui = datetime.now()
    age_actuel = aujourd_hui.year - date_obj.year
    if (aujourd_hui.month, aujourd_hui.day) < (date_obj.month, date_obj.day):
        age_actuel -= 1
    
    return {
        "date_naissance": date_naissance,
        "generation": generation,
        "age_actuel": age_actuel
    }


def calculer_situation_actuelle(
    trimestres_actuels: int,
    en_activite: bool,
    age_actuel: int,
    age_depart_prevu: Optional[int],
    generation: int
) -> Dict:
    """
    Calcule la situation actuelle avec projection si en activité.
    
    Args:
        trimestres_actuels: Trimestres validés actuellement
        en_activite: Boolean indiquant si la personne est en activité
        age_actuel: Âge actuel
        age_depart_prevu: Âge de départ prévu (si en activité)
        generation: Année de naissance
    
    Returns:
        Dict avec situation actuelle et projection
    """
    # Trimestres requis selon génération (simplifié)
    trimestres_requis = obtenir_trimestres_requis(generation)
    
    # Projection si en activité
    trimestres_projetes = trimestres_actuels
    trimestres_futurs = 0
    
    if en_activite and age_depart_prevu:
        annees_restantes = age_depart_prevu - age_actuel
        if annees_restantes > 0:
            trimestres_futurs = annees_restantes * 4
            trimestres_projetes = trimestres_actuels + trimestres_futurs
    
    trimestres_manquants = max(0, trimestres_requis - trimestres_projetes)
    
    return {
        "trimestres_valides": trimestres_actuels,
        "trimestres_projetes": trimestres_projetes,
        "trimestres_futurs": trimestres_futurs,
        "trimestres_requis": trimestres_requis,
        "trimestres_manquants": trimestres_manquants,
        "en_activite": en_activite,
        "projection_age": age_depart_prevu if en_activite else None
    }


def obtenir_trimestres_requis(generation: int) -> int:
    """
    Retourne le nombre de trimestres requis selon la génération.
    
    Args:
        generation: Année de naissance
    
    Returns:
        Nombre de trimestres requis
    """
    if generation <= 1962:
        return 169
    elif generation == 1963:
        return 170
    elif generation == 1964:
        return 171
    else:  # 1965 et suivantes
        return 172


def calculer_ages_cles(date_naissance: str) -> Dict:
    """
    Calcule les âges clés (légal, taux plein automatique).
    
    Args:
        date_naissance: Date au format JJ/MM/AAAA
    
    Returns:
        Dict avec âges clés et dates
    """
    date_obj = datetime.strptime(date_naissance, "%d/%m/%Y")
    generation = date_obj.year
    
    # Âge légal selon génération (simplifié)
    if generation <= 1960:
        age_legal_ans = 62
        age_legal_mois = 0
    elif generation == 1961:
        age_legal_ans = 62
        age_legal_mois = 3
    elif generation == 1962:
        age_legal_ans = 62
        age_legal_mois = 6
    elif generation == 1963:
        age_legal_ans = 62
        age_legal_mois = 9
    elif generation == 1964:
        age_legal_ans = 63
        age_legal_mois = 0
    elif generation == 1965:
        age_legal_ans = 63
        age_legal_mois = 3
    elif generation == 1966:
        age_legal_ans = 63
        age_legal_mois = 6
    elif generation == 1967:
        age_legal_ans = 63
        age_legal_mois = 9
    else:  # 1968 et suivantes
        age_legal_ans = 64
        age_legal_mois = 0
    
    # Calcul date âge légal
    date_age_legal = datetime(
        date_obj.year + age_legal_ans,
        date_obj.month,
        date_obj.day
    )
    if age_legal_mois > 0:
        mois_cible = date_age_legal.month + age_legal_mois
        annee_cible = date_age_legal.year
        if mois_cible > 12:
            mois_cible -= 12
            annee_cible += 1
        date_age_legal = datetime(annee_cible, mois_cible, date_obj.day)
    
    # Date d'effet (1er jour du mois suivant)
    mois_effet = date_age_legal.month + 1
    annee_effet = date_age_legal.year
    if mois_effet > 12:
        mois_effet = 1
        annee_effet += 1
    date_effet_legal = f"01/{mois_effet:02d}/{annee_effet}"
    
    # Taux plein automatique à 67 ans
    date_67_ans = datetime(date_obj.year + 67, date_obj.month, date_obj.day)
    mois_effet_67 = date_67_ans.month + 1
    annee_effet_67 = date_67_ans.year
    if mois_effet_67 > 12:
        mois_effet_67 = 1
        annee_effet_67 += 1
    date_effet_67_ans = f"01/{mois_effet_67:02d}/{annee_effet_67}"
    
    return {
        "age_legal": {
            "ans": age_legal_ans,
            "mois": age_legal_mois,
            "date": date_age_legal.strftime("%d/%m/%Y")
        },
        "taux_plein_auto": {
            "ans": 67,
            "mois": 0,
            "date": date_67_ans.strftime("%d/%m/%Y")
        },
        "date_effet_legal": date_effet_legal,
        "date_effet_67_ans": date_effet_67_ans
    }


def calculer_scenarios_depart(
    date_naissance: str,
    trimestres_valides: int,
    trimestres_requis: int,
    ages_cles: Dict
) -> Dict:
    """
    Calcule les différents scénarios de départ (âge légal, 67 ans, surcote).
    
    Args:
        date_naissance: Date de naissance
        trimestres_valides: Trimestres validés (projetés)
        trimestres_requis: Trimestres requis
        ages_cles: Dict avec les âges clés
    
    Returns:
        Dict avec les 3 scénarios
    """
    trimestres_manquants = max(0, trimestres_requis - trimestres_valides)
    
    # Scénario 1 : Départ à l'âge légal
    if trimestres_manquants > 0:
        decote_pct = trimestres_manquants * DECOTE_PAR_TRIMESTRE
        taux = TAUX_PLEIN - decote_pct
        taux = max(taux, TAUX_MINIMAL)
        scenario_1 = {
            "taux": round(taux, 2),
            "type": "decote",
            "trimestres_manquants": trimestres_manquants,
            "decote_pct": round(decote_pct, 3)
        }
    else:
        scenario_1 = {
            "taux": TAUX_PLEIN,
            "type": "taux_plein",
            "trimestres_manquants": 0,
            "decote_pct": 0
        }
    
    # Scénario 2 : Départ à 67 ans
    scenario_2 = {
        "taux": TAUX_PLEIN,
        "type": "taux_plein_auto",
        "description": "Taux plein garanti quel que soit le nombre de trimestres"
    }
    
    # Scénario 3 : Surcote (si trimestres excédentaires)
    scenario_3 = None
    if trimestres_valides > trimestres_requis:
        trimestres_surcote = trimestres_valides - trimestres_requis
        majoration_pct = trimestres_surcote * SURCOTE_PAR_TRIMESTRE
        taux_majore = TAUX_PLEIN * (1 + majoration_pct / 100)
        scenario_3 = {
            "trimestres_surcote": trimestres_surcote,
            "majoration_pct": round(majoration_pct, 2),
            "taux_majore": round(taux_majore, 2)
        }
    
    return {
        "scenario_1_age_legal": scenario_1,
        "scenario_2_67_ans": scenario_2,
        "scenario_3_surcote": scenario_3
    }


def calculer_pensions_complementaires(
    date_naissance: str,
    date_depart: str,
    trimestres_valides: int,
    trimestres_requis: int,
    points_agirc_arrco: int,
    points_ircantec_a: int,
    points_ircantec_b: int,
    points_rco: int,
    points_rci: int
) -> Dict:
    """
    Calcule les pensions des régimes complémentaires.
    
    Args:
        date_naissance: Date de naissance
        date_depart: Date de départ prévue
        trimestres_valides: Trimestres validés
        trimestres_requis: Trimestres requis
        points_xxx: Points acquis dans chaque régime
    
    Returns:
        Dict avec montants bruts annuels et mensuels
    """
    # AGIRC-ARRCO
    montant_aa_annuel = points_agirc_arrco * VALEUR_POINT_AGIRC_ARRCO
    montant_aa_mensuel = montant_aa_annuel / 12
    
    # IRCANTEC
    points_ircantec_total = points_ircantec_a + points_ircantec_b
    montant_irc_annuel = points_ircantec_total * VALEUR_POINT_IRCANTEC
    montant_irc_mensuel = montant_irc_annuel / 12
    
    # RCI
    montant_rci_annuel = (points_rco * VALEUR_POINT_RCO) + (points_rci * VALEUR_POINT_RCI)
    montant_rci_mensuel = montant_rci_annuel / 12
    
    # Total
    total_brut_annuel = montant_aa_annuel + montant_irc_annuel + montant_rci_annuel
    total_brut_mensuel = total_brut_annuel / 12
    
    return {
        "agirc_arrco": {
            "points": points_agirc_arrco,
            "montant_annuel_brut": round(montant_aa_annuel, 2),
            "montant_mensuel_brut": round(montant_aa_mensuel, 2)
        },
        "ircantec": {
            "points_a": points_ircantec_a,
            "points_b": points_ircantec_b,
            "montant_annuel_brut": round(montant_irc_annuel, 2),
            "montant_mensuel_brut": round(montant_irc_mensuel, 2)
        },
        "rci": {
            "points_rco": points_rco,
            "points_rci": points_rci,
            "montant_annuel_brut": round(montant_rci_annuel, 2),
            "montant_mensuel_brut": round(montant_rci_mensuel, 2)
        },
        "total_brut_annuel": round(total_brut_annuel, 2),
        "total_brut_mensuel": round(total_brut_mensuel, 2),
        "note": "Montants bruts - Prélèvements sociaux à déduire (CSG, CRDS)"
    }


def verifier_carriere_longue(
    trimestres_avant_16: int,
    trimestres_avant_18: int,
    trimestres_avant_20: int,
    trimestres_avant_21: int
) -> Dict:
    """
    Vérifie l'éligibilité potentielle au dispositif carrière longue.
    
    Args:
        trimestres_avant_XX: Trimestres validés avant certains âges
    
    Returns:
        Dict avec statut et action recommandée
    """
    eligible = False
    cas_detecte = None
    action = None
    
    if trimestres_avant_16 >= 4:
        eligible = True
        cas_detecte = "avant_16_ans"
        action = "Déclencher SKILL_racl.md pour analyse détaillée - Départ possible dès 58 ans"
    elif trimestres_avant_18 >= 4:
        eligible = True
        cas_detecte = "avant_18_ans"
        action = "Déclencher SKILL_racl.md pour analyse détaillée - Départ possible dès 60 ans"
    elif trimestres_avant_20 >= 4:
        eligible = True
        cas_detecte = "avant_20_ans"
        action = "Déclencher SKILL_racl.md pour analyse détaillée - Départ possible selon génération"
    elif trimestres_avant_21 >= 4:
        eligible = True
        cas_detecte = "avant_21_ans"
        action = "Déclencher SKILL_racl.md pour analyse détaillée - Départ possible dès 63 ans"
    
    return {
        "eligible": eligible,
        "cas_detecte": cas_detecte,
        "trimestres_avant_16_ans": trimestres_avant_16,
        "trimestres_avant_18_ans": trimestres_avant_18,
        "trimestres_avant_20_ans": trimestres_avant_20,
        "trimestres_avant_21_ans": trimestres_avant_21,
        "action": action if eligible else "Pas d'éligibilité carrière longue détectée"
    }


def calculer_rachat_trimestres(trimestres_manquants: int, age_actuel: int) -> Dict:
    """
    Calcule les options de rachat de trimestres.
    
    Args:
        trimestres_manquants: Nombre de trimestres manquants
        age_actuel: Âge actuel de la personne
    
    Returns:
        Dict avec options de rachat
    """
    if trimestres_manquants <= 0:
        return {
            "trimestres_rachetables": 0,
            "impact": "Déjà au taux plein",
            "cout_estime_min": 0,
            "cout_estime_max": 0
        }
    
    # Maximum 12 trimestres rachetables
    trimestres_rachetables = min(trimestres_manquants, 12)
    
    # Estimation coût (simplifié - varie selon âge et revenus)
    # Ordre de grandeur : 4000-6000€ par trimestre
    cout_par_trimestre_min = 4000
    cout_par_trimestre_max = 6000
    
    cout_estime_min = trimestres_rachetables * cout_par_trimestre_min
    cout_estime_max = trimestres_rachetables * cout_par_trimestre_max
    
    return {
        "trimestres_rachetables": trimestres_rachetables,
        "impact": f"Départ au taux plein possible avec rachat de {trimestres_rachetables} trimestre(s)",
        "cout_estime_min": cout_estime_min,
        "cout_estime_max": cout_estime_max,
        "note": "Coûts estimatifs - Voir barèmes VPLR 2025 pour calcul précis"
    }


def generer_controles_coherence(
    controles: List[Dict],
    trimestres_valides: int,
    age_actuel: int,
    lacunes_carriere: List[Dict],
    points_total: int
) -> None:
    """
    Génère les contrôles de cohérence.
    
    Args:
        controles: Liste à remplir avec les contrôles
        trimestres_valides: Trimestres validés
        age_actuel: Âge actuel
        lacunes_carriere: Liste des lacunes détectées
        points_total: Total des points complémentaires
    """
    # ARC_C02 : Trimestres négatifs ou > 200
    if trimestres_valides < 0 or trimestres_valides > 200:
        controles.append({
            "code": "ARC_C02",
            "type": "ERREUR",
            "message": f"Nombre de trimestres incohérent : {trimestres_valides}"
        })
    
    # ARC_C05 : Lacunes > 2 ans
    for lacune in lacunes_carriere:
        duree = lacune.get("annee_fin", 0) - lacune.get("annee_debut", 0)
        if duree > 2:
            controles.append({
                "code": "ARC_C05",
                "type": "AVERTISSEMENT",
                "message": f"Lacune de carrière de {duree} ans ({lacune.get('annee_debut')}-{lacune.get('annee_fin')}) non justifiée"
            })
    
    # ARC_C06 : Points complémentaires nuls avec carrière complète
    if points_total == 0 and age_actuel > 40:
        controles.append({
            "code": "ARC_C06",
            "type": "AVERTISSEMENT",
            "message": "Absence de points complémentaires malgré une carrière complète"
        })


def generer_alertes(
    alertes: Dict,
    situation_actuelle: Dict,
    lacunes_carriere: List[Dict],
    periodes_etranger: List[Dict],
    carriere_longue: Dict,
    en_activite: bool
) -> None:
    """
    Génère les alertes (rouge/orange/jaune).
    
    Args:
        alertes: Dict à remplir avec les alertes
        situation_actuelle: Situation actuelle calculée
        lacunes_carriere: Lacunes détectées
        periodes_etranger: Périodes à l'étranger
        carriere_longue: Résultat carrière longue
        en_activite: Boolean indiquant si en activité
    """
    trimestres_manquants = situation_actuelle["trimestres_manquants"]
    
    # Alertes ROUGES
    if len(lacunes_carriere) > 2:
        alertes["rouge"].append(f"Relevé de carrière incomplet ({len(lacunes_carriere)} lacunes détectées)")
    
    if len(periodes_etranger) > 0:
        alertes["rouge"].append(f"{len(periodes_etranger)} période(s) à l'étranger détectée(s) - Vérifier prise en compte")
    
    # Alertes ORANGE
    if 1 <= trimestres_manquants <= 4:
        alertes["orange"].append(f"Proche du taux plein ({trimestres_manquants} trimestres manquants) - Envisager rachat VPLR")
    
    if carriere_longue["eligible"]:
        alertes["orange"].append(f"Carrière longue potentielle détectée ({carriere_longue['cas_detecte']}) - Analyser éligibilité RACL")
    
    for lacune in lacunes_carriere:
        duree = lacune.get("annee_fin", 0) - lacune.get("annee_debut", 0)
        if duree <= 2:
            alertes["orange"].append(f"Lacune de carrière courte ({duree} ans) - Vérifier justification")
    
    # Alertes JAUNES
    if en_activite:
        alertes["jaune"].append(f"Projection d'activité nécessaire jusqu'à {situation_actuelle['projection_age']} ans")


# ============================================================================
# SECTION 3 : FONCTIONS UTILITAIRES
# ============================================================================

def estimer_token_cost() -> int:
    """
    Estime le coût en tokens pour une analyse complète.
    
    Returns:
        Estimation du nombre de tokens
    """
    return 9000  # Estimation basée sur skill_info


# ============================================================================
# SECTION 4 : EXEMPLE D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    # Exemple d'utilisation du script
    params_exemple = {
        "date_naissance": "15/03/1965",
        "trimestres_valides_actuels": 165,
        "en_activite": True,
        "age_depart_prevu": 64,
        "points_agirc_arrco": 5432,
        "points_ircantec_a": 1234,
        "points_ircantec_b": 567,
        "points_rco": 234,
        "points_rci": 456,
        "trimestres_avant_20_ans": 5,
        "lacunes_carriere": [
            {"annee_debut": 2010, "annee_fin": 2011, "raison": "Non reporté"}
        ],
        "periodes_etranger": []
    }
    
    result = api_handler(params_exemple)
    
    print("=== ANALYSE RELEVÉ DE CARRIÈRE ===")
    print(f"\nProfil : Génération {result['profil']['generation']}, {result['profil']['age_actuel']} ans")
    print(f"\nTrimestres actuels : {result['situation_actuelle']['trimestres_valides']}")
    print(f"Trimestres projetés : {result['situation_actuelle']['trimestres_projetes']}")
    print(f"Trimestres requis : {result['situation_actuelle']['trimestres_requis']}")
    print(f"Trimestres manquants : {result['situation_actuelle']['trimestres_manquants']}")
    
    print(f"\nÂge légal : {result['ages_cles']['age_legal']['ans']} ans {result['ages_cles']['age_legal']['mois']} mois")
    print(f"Date effet : {result['ages_cles']['date_effet_legal']}")
    
    print(f"\nScénario âge légal : Taux {result['scenarios']['scenario_1_age_legal']['taux']}%")
    
    if result['carriere_longue']['eligible']:
        print(f"\n⚠️ CARRIÈRE LONGUE DÉTECTÉE : {result['carriere_longue']['action']}")
    
    print(f"\nPensions complémentaires estimées : {result['pensions_complementaires']['total_brut_annuel']:.2f}€/an")
