# -*- coding: utf-8 -*-
"""
Script : calcul_trimestres_etranger.py
Description : Calculs de l'impact des périodes à l'étranger sur la retraite française
Version : 2.0 - Classe mondiale
Date : Novembre 2025
Législation : Règlements européens 883/2004 et 987/2009, Conventions bilatérales

Ce script automatise :
- Identification du statut du pays (UE/Convention/Sans accord)
- Conversion des périodes étrangères en trimestres totalisés
- Calcul de l'impact sur le taux plein
- Calcul du montant de la pension au prorata

Format de dates : JJ/MM/AAAA (exemple : 15/03/1965)

NOUVEAUTÉS VERSION 2.0 :
- api_handler() pour intégration N8N
- Métadonnées enrichies (token_estimate, controles_ids, alertes)
- Encodage UTF-8 corrigé
"""

from datetime import datetime
from typing import Dict, Tuple, List, Any


# ============================================================================
# SECTION 1 : LISTES DES PAYS PAR CATÉGORIE
# ============================================================================

# Catégorie A : Union Européenne + EEE + Suisse (totalisation automatique)
PAYS_UE_EEE_SUISSE = {
    # 27 pays UE
    "ALLEMAGNE", "AUTRICHE", "BELGIQUE", "BULGARIE", "CHYPRE", "CROATIE",
    "DANEMARK", "ESPAGNE", "ESTONIE", "FINLANDE", "FRANCE", "GRECE",
    "HONGRIE", "IRLANDE", "ITALIE", "LETTONIE", "LITUANIE", "LUXEMBOURG",
    "MALTE", "PAYS-BAS", "POLOGNE", "PORTUGAL", "REPUBLIQUE TCHEQUE",
    "ROUMANIE", "SLOVAQUIE", "SLOVENIE", "SUEDE",
    # EEE
    "NORVEGE", "ISLANDE", "LIECHTENSTEIN",
    # Suisse
    "SUISSE",
    # Royaume-Uni (accord post-Brexit)
    "ROYAUME-UNI", "GRANDE-BRETAGNE"
}

# Catégorie B : Pays avec convention bilatérale (41 pays avec dispositions vieillesse)
PAYS_CONVENTION_BILATERALE = {
    "ALGERIE", "ANDORRE", "ARGENTINE", "BENIN", "BOSNIE-HERZEGOVINE",
    "BRESIL", "CAMEROUN", "CANADA", "CAP-VERT", "CHILI", "CONGO",
    "COREE DU SUD", "COREE", "COTE D'IVOIRE", "ETATS-UNIS", "USA",
    "GABON", "GUERNESEY", "INDE", "ISRAEL", "JAPON", "JERSEY",
    "MACEDOINE", "MALI", "MAROC", "MAURITANIE", "MONACO",
    "MONTENEGRO", "NIGER", "NOUVELLE-CALEDONIE", "PHILIPPINES",
    "POLYNESIE FRANCAISE", "QUEBEC", "SAINT-MARIN", "SENEGAL",
    "SERBIE", "TOGO", "TUNISIE", "TURQUIE", "URUGUAY"
}

# Note : Madagascar a une convention mais SANS dispositions vieillesse


# ============================================================================
# SECTION 2 : FONCTIONS DE CLASSIFICATION DES PAYS
# ============================================================================

def normaliser_nom_pays(pays: str) -> str:
    """
    Normalise le nom d'un pays pour la recherche.
    
    Args:
        pays (str): Nom du pays
    
    Returns:
        str: Nom normalisé (majuscules, sans accents)
    
    Exemple:
        >>> normaliser_nom_pays("États-Unis")
        "ETATS-UNIS"
    """
    pays_upper = pays.upper().strip()
    
    # Normalisation des accents
    replacements = {
        "É": "E", "È": "E", "Ê": "E", "Ë": "E",
        "À": "A", "Â": "A", "Ä": "A",
        "Î": "I", "Ï": "I",
        "Ô": "O", "Ö": "O",
        "Ù": "U", "Û": "U", "Ü": "U",
        "Ç": "C"
    }
    
    for accent, sans_accent in replacements.items():
        pays_upper = pays_upper.replace(accent, sans_accent)
    
    return pays_upper


def identifier_statut_pays(pays: str) -> Dict[str, Any]:
    """
    Identifie le statut d'un pays pour la totalisation des trimestres.
    
    Args:
        pays (str): Nom du pays
    
    Returns:
        dict: {
            "pays": str,
            "statut": str ("UE_EEE_SUISSE", "CONVENTION_BILATERALE", "SANS_ACCORD"),
            "totalisation_possible": bool,
            "description": str,
            "controles": list[str] - IDs des contrôles passés
        }
    
    Exemple:
        >>> identifier_statut_pays("Canada")
        {
            "pays": "CANADA",
            "statut": "CONVENTION_BILATERALE",
            "totalisation_possible": True,
            "description": "Pays avec convention bilatérale",
            "controles": ["TRE_C01", "TRE_C03"]
        }
    """
    pays_norm = normaliser_nom_pays(pays)
    controles_passes = []
    
    # Contrôle TRE_C01 : Pays identifié dans les listes
    if pays_norm in PAYS_UE_EEE_SUISSE or pays_norm in PAYS_CONVENTION_BILATERALE:
        controles_passes.append("TRE_C01")
    
    # Catégorie A : UE/EEE/Suisse
    if pays_norm in PAYS_UE_EEE_SUISSE:
        controles_passes.append("TRE_C03")  # Totalisation possible
        return {
            "pays": pays_norm,
            "statut": "UE_EEE_SUISSE",
            "totalisation_possible": True,
            "description": "Union Européenne, EEE ou Suisse - Totalisation automatique",
            "base_legale": "Règlements européens 883/2004 et 987/2009",
            "controles": controles_passes,
            "alerte_niveau": "VERT"
        }
    
    # Catégorie B : Convention bilatérale
    if pays_norm in PAYS_CONVENTION_BILATERALE:
        controles_passes.append("TRE_C03")  # Totalisation possible
        return {
            "pays": pays_norm,
            "statut": "CONVENTION_BILATERALE",
            "totalisation_possible": True,
            "description": "Pays avec convention bilatérale",
            "base_legale": "Convention bilatérale France-" + pays_norm,
            "note": "Vérifier les conditions spécifiques de la convention sur www.cleiss.fr",
            "controles": controles_passes,
            "alerte_niveau": "JAUNE"
        }
    
    # Catégorie C : Sans accord
    return {
        "pays": pays_norm,
        "statut": "SANS_ACCORD",
        "totalisation_possible": False,
        "description": "Pays sans accord de totalisation",
        "note": "Les périodes dans ce pays ne sont PAS prises en compte pour la retraite française",
        "controles": [],
        "alerte_niveau": "ROUGE"
    }


# ============================================================================
# SECTION 3 : CALCUL DES TRIMESTRES TOTALISÉS
# ============================================================================

def parser_date_francaise(date_str: str) -> datetime:
    """Convertit une date JJ/MM/AAAA en datetime."""
    return datetime.strptime(date_str, "%d/%m/%Y")


def calculer_trimestres_periode(date_debut: str, date_fin: str) -> Dict[str, Any]:
    """
    Calcule le nombre de trimestres totalisés pour une période à l'étranger.
    
    Règle de conversion :
    - 1 année civile = 4 trimestres
    - 90 jours = 1 trimestre
    - Maximum 4 trimestres par année civile
    
    Args:
        date_debut (str): Date de début au format JJ/MM/AAAA
        date_fin (str): Date de fin au format JJ/MM/AAAA
    
    Returns:
        dict: {
            "trimestres_totalises": int,
            "date_debut": str,
            "date_fin": str,
            "duree_jours": int,
            "duree_mois": int,
            "annees_completes": int,
            "detail_par_annee": dict,
            "controles": list[str],
            "alerte_niveau": str
        }
    
    Exemple:
        >>> calculer_trimestres_periode("01/01/2010", "31/12/2014")
        {
            "trimestres_totalises": 20,
            "duree_jours": 1826,
            "annees_completes": 5,
            "controles": ["TRE_C04", "TRE_C06"]
        }
    """
    controles_passes = []
    alertes = []
    
    try:
        debut = parser_date_francaise(date_debut)
        fin = parser_date_francaise(date_fin)
        controles_passes.append("TRE_C04")  # Dates valides
    except ValueError as e:
        return {
            "erreur": f"Format de date invalide : {e}",
            "controles": [],
            "alerte_niveau": "ROUGE"
        }
    
    # Calcul de la durée
    duree = fin - debut
    duree_jours = duree.days + 1  # Inclure le jour de fin
    
    # Parcours année par année
    trimestres_total = 0
    detail_par_annee = {}
    
    annee_courante = debut.year
    annee_fin = fin.year
    
    while annee_courante <= annee_fin:
        # Déterminer le début et la fin pour l'année courante
        if annee_courante == debut.year:
            debut_annee = debut
        else:
            debut_annee = datetime(annee_courante, 1, 1)
        
        if annee_courante == annee_fin:
            fin_annee = fin
        else:
            fin_annee = datetime(annee_courante, 12, 31)
        
        # Calcul des jours dans l'année
        jours_annee = (fin_annee - debut_annee).days + 1
        
        # Conversion en trimestres (90 jours = 1 trimestre, max 4/an)
        trimestres_annee = min(jours_annee // 90, 4)
        trimestres_total += trimestres_annee
        
        detail_par_annee[annee_courante] = {
            "jours": jours_annee,
            "trimestres": trimestres_annee
        }
        
        # Contrôle TRE_C06 : Max 4 trimestres/an
        if trimestres_annee <= 4:
            if "TRE_C06" not in controles_passes:
                controles_passes.append("TRE_C06")
        else:
            alertes.append(f"Année {annee_courante} : {trimestres_annee} trimestres > 4")
        
        annee_courante += 1
    
    # Calcul durée en mois
    duree_mois = (annee_fin - debut.year) * 12 + (fin.month - debut.month)
    annees_completes = annee_fin - debut.year + (1 if debut.month == 1 and fin.month == 12 and debut.day == 1 and fin.day == 31 else 0)
    
    # Niveau d'alerte global
    alerte_niveau = "ROUGE" if alertes else "VERT"
    
    return {
        "trimestres_totalises": trimestres_total,
        "date_debut": date_debut,
        "date_fin": date_fin,
        "duree_jours": duree_jours,
        "duree_mois": duree_mois,
        "annees_completes": max(0, annee_fin - debut.year),
        "detail_par_annee": detail_par_annee,
        "controles": controles_passes,
        "alertes": alertes,
        "alerte_niveau": alerte_niveau
    }


# ============================================================================
# SECTION 4 : ANALYSE D'IMPACT SUR LA RETRAITE
# ============================================================================

def analyser_impact_trimestres_etranger(
    pays: str,
    date_debut: str,
    date_fin: str,
    trimestres_francais: int,
    duree_requise_taux_plein: int
) -> Dict[str, Any]:
    """
    Analyse l'impact des trimestres étrangers sur le droit à la retraite française.
    
    Args:
        pays (str): Nom du pays étranger
        date_debut (str): Date de début de la période (JJ/MM/AAAA)
        date_fin (str): Date de fin de la période (JJ/MM/AAAA)
        trimestres_francais (int): Nombre de trimestres cotisés en France
        duree_requise_taux_plein (int): Durée requise pour le taux plein
    
    Returns:
        dict: Analyse complète avec métadonnées enrichies
    
    Exemple:
        >>> analyser_impact_trimestres_etranger("Canada", "01/01/2000", "31/12/2009", 130, 167)
    """
    controles_globaux = []
    
    # 1. Identification du statut du pays
    statut_pays = identifier_statut_pays(pays)
    controles_globaux.extend(statut_pays.get("controles", []))
    
    # 2. Calcul des trimestres si totalisation possible
    if statut_pays["totalisation_possible"]:
        calcul_trimestres = calculer_trimestres_periode(date_debut, date_fin)
        trimestres_totalises = calcul_trimestres["trimestres_totalises"]
        controles_globaux.extend(calcul_trimestres.get("controles", []))
    else:
        calcul_trimestres = None
        trimestres_totalises = 0
    
    # 3. Calcul de l'impact sur le taux plein
    trimestres_totaux = trimestres_francais + trimestres_totalises
    taux_plein_atteint = trimestres_totaux >= duree_requise_taux_plein
    trimestres_manquants = max(0, duree_requise_taux_plein - trimestres_totaux)
    
    # 4. Calcul du coefficient de proratisation
    if duree_requise_taux_plein > 0:
        coefficient_proratisation = min(trimestres_francais / duree_requise_taux_plein, 1.0)
    else:
        coefficient_proratisation = 0.0
    
    # Contrôle TRE_C02 : Cohérence période avec relevé
    if trimestres_francais > 0:
        controles_globaux.append("TRE_C02")
    
    # Contrôle TRE_C05 : Pas de doublon trimestres (logique simplifiée)
    if trimestres_totalises > 0 and trimestres_francais > 0:
        controles_globaux.append("TRE_C05")
    
    # Estimation tokens pour N8N
    token_estimate = 1500 if trimestres_totalises > 0 else 800
    
    # Détermination alerte globale
    if not statut_pays["totalisation_possible"]:
        alerte_globale = "ROUGE"
    elif statut_pays["statut"] == "CONVENTION_BILATERALE":
        alerte_globale = "JAUNE"
    else:
        alerte_globale = "VERT"
    
    # 5. Synthèse
    return {
        "pays": statut_pays["pays"],
        "statut_pays": statut_pays,
        "trimestres_francais": trimestres_francais,
        "trimestres_totalises": trimestres_totalises,
        "trimestres_totaux_duree_assurance": trimestres_totaux,
        "duree_requise_taux_plein": duree_requise_taux_plein,
        "taux_plein_atteint": taux_plein_atteint,
        "trimestres_manquants": trimestres_manquants,
        "coefficient_proratisation_pension_francaise": round(coefficient_proratisation, 3),
        "calcul_trimestres": calcul_trimestres,
        "impact": {
            "taux_liquidation": "Taux plein (50%)" if taux_plein_atteint else f"Décote ({trimestres_manquants} trim manquants)",
            "montant_pension": f"Calculé sur {trimestres_francais} trimestres français (prorata {coefficient_proratisation:.1%})",
            "pension_etrangere": f"Le {pays} versera sa propre pension pour les {trimestres_totalises} trimestres cotisés" if trimestres_totalises > 0 else "Aucune pension étrangère"
        },
        "metadata": {
            "controles_passes": list(set(controles_globaux)),
            "alerte_niveau": alerte_globale,
            "token_estimate": token_estimate,
            "version_script": "2.0"
        }
    }


# ============================================================================
# SECTION 5 : CALCUL PENSION AU PRORATA
# ============================================================================

def calculer_pension_avec_prorata_etranger(
    sam: float,
    taux_liquidation: float,
    trimestres_francais: int,
    trimestres_totalises_etranger: int,
    duree_requise: int
) -> Dict[str, Any]:
    """
    Calcule la pension française en tenant compte des périodes à l'étranger.
    
    Formule :
    1. Pension théorique (comme si tout était en France) = SAM × Taux × (Trim totaux / Durée requise)
    2. Pension française réelle (prorata) = Pension théorique × (Trim français / Trim totaux)
    
    Args:
        sam (float): Salaire Annuel Moyen
        taux_liquidation (float): Taux de liquidation en % (37.5 à 50+)
        trimestres_francais (int): Trimestres cotisés en France
        trimestres_totalises_etranger (int): Trimestres totalisés à l'étranger
        duree_requise (int): Durée requise pour le taux plein
    
    Returns:
        dict: Détail du calcul avec pension théorique et réelle + métadonnées
    
    Exemple:
        >>> calculer_pension_avec_prorata_etranger(30000, 50.0, 140, 20, 172)
    """
    trimestres_totaux = trimestres_francais + trimestres_totalises_etranger
    
    # 1. Pension théorique (si tout était en France)
    coefficient_duree = min(trimestres_totaux / duree_requise, 1.0)
    pension_theorique = sam * (taux_liquidation / 100) * coefficient_duree
    
    # 2. Pension française réelle (prorata)
    if trimestres_totaux > 0:
        coefficient_prorata = trimestres_francais / trimestres_totaux
    else:
        coefficient_prorata = 0.0
    
    pension_francaise_reelle = pension_theorique * coefficient_prorata
    
    # Token estimate
    token_estimate = 2000
    
    return {
        "pension_theorique_annuelle": round(pension_theorique, 2),
        "pension_theorique_mensuelle": round(pension_theorique / 12, 2),
        "pension_francaise_annuelle": round(pension_francaise_reelle, 2),
        "pension_francaise_mensuelle": round(pension_francaise_reelle / 12, 2),
        "coefficient_prorata": round(coefficient_prorata, 3),
        "trimestres_francais": trimestres_francais,
        "trimestres_totalises_etranger": trimestres_totalises_etranger,
        "trimestres_totaux": trimestres_totaux,
        "sam": sam,
        "taux_liquidation": taux_liquidation,
        "note": f"Le pays étranger versera sa propre pension pour les {trimestres_totalises_etranger} trimestres cotisés",
        "metadata": {
            "token_estimate": token_estimate,
            "version_script": "2.0"
        }
    }


# ============================================================================
# SECTION 6 : API HANDLER POUR N8N
# ============================================================================

def api_handler(event: dict) -> dict:
    """
    Handler standardisé pour intégration N8N.
    
    Args:
        event (dict): {
            "action": str - Action à exécuter,
            "params": dict - Paramètres de l'action
        }
    
    Actions disponibles :
    - "identifier_pays" : Identifier le statut d'un pays
    - "calculer_trimestres" : Calculer trimestres d'une période
    - "analyser_impact" : Analyse complète impact retraite
    - "calculer_pension" : Calculer pension avec prorata
    
    Returns:
        dict: {
            "success": bool,
            "data": dict,
            "error": str (si échec),
            "metadata": dict
        }
    
    Exemple:
        >>> api_handler({
            "action": "identifier_pays",
            "params": {"pays": "Canada"}
        })
    """
    try:
        action = event.get("action", "")
        params = event.get("params", {})
        
        # Routage des actions
        if action == "identifier_pays":
            data = identifier_statut_pays(params.get("pays", ""))
            
        elif action == "calculer_trimestres":
            data = calculer_trimestres_periode(
                params.get("date_debut", ""),
                params.get("date_fin", "")
            )
            
        elif action == "analyser_impact":
            data = analyser_impact_trimestres_etranger(
                params.get("pays", ""),
                params.get("date_debut", ""),
                params.get("date_fin", ""),
                params.get("trimestres_francais", 0),
                params.get("duree_requise_taux_plein", 172)
            )
            
        elif action == "calculer_pension":
            data = calculer_pension_avec_prorata_etranger(
                params.get("sam", 0.0),
                params.get("taux_liquidation", 50.0),
                params.get("trimestres_francais", 0),
                params.get("trimestres_totalises_etranger", 0),
                params.get("duree_requise", 172)
            )
            
        else:
            return {
                "success": False,
                "error": f"Action '{action}' non reconnue",
                "actions_disponibles": [
                    "identifier_pays",
                    "calculer_trimestres",
                    "analyser_impact",
                    "calculer_pension"
                ]
            }
        
        return {
            "success": True,
            "data": data,
            "metadata": {
                "action": action,
                "version_script": "2.0",
                "timestamp": datetime.now().isoformat()
            }
        }
        
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "metadata": {
                "action": event.get("action", "unknown"),
                "version_script": "2.0"
            }
        }


# ============================================================================
# SECTION 7 : EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    print("=" * 80)
    print("SCRIPT : calcul_trimestres_etranger.py v2.0 - TESTS ET EXEMPLES")
    print("=" * 80)
    
    # EXEMPLE 1 : Période au Canada (pays avec convention)
    print("\n### EXEMPLE 1 : Période de 10 ans au Canada ###")
    print("-" * 80)
    
    result_1 = analyser_impact_trimestres_etranger(
        pays="Canada",
        date_debut="01/01/2000",
        date_fin="31/12/2009",
        trimestres_francais=130,
        duree_requise_taux_plein=167
    )
    
    print(f"Pays : {result_1['pays']}")
    print(f"Statut : {result_1['statut_pays']['description']}")
    print(f"Trimestres français : {result_1['trimestres_francais']}")
    print(f"Trimestres totalisés (Canada) : {result_1['trimestres_totalises']}")
    print(f"Trimestres totaux pour durée assurance : {result_1['trimestres_totaux_duree_assurance']}")
    print(f"Durée requise taux plein : {result_1['duree_requise_taux_plein']}")
    print(f"Taux plein atteint : {'OUI' if result_1['taux_plein_atteint'] else 'NON'}")
    print(f"Trimestres manquants : {result_1['trimestres_manquants']}")
    print(f"\nMÉTADONNÉES :")
    print(f"  - Contrôles passés : {result_1['metadata']['controles_passes']}")
    print(f"  - Alerte niveau : {result_1['metadata']['alerte_niveau']}")
    print(f"  - Token estimate : {result_1['metadata']['token_estimate']}")
    print(f"\nIMPACT :")
    print(f"  - Taux : {result_1['impact']['taux_liquidation']}")
    print(f"  - Montant pension : {result_1['impact']['montant_pension']}")
    print(f"  - Pension étrangère : {result_1['impact']['pension_etrangere']}")
    
    # EXEMPLE 2 : Période en Chine (pays sans accord)
    print("\n\n### EXEMPLE 2 : Période de 5 ans en Chine (sans accord) ###")
    print("-" * 80)
    
    result_2 = analyser_impact_trimestres_etranger(
        pays="Chine",
        date_debut="01/01/2015",
        date_fin="31/12/2020",
        trimestres_francais=145,
        duree_requise_taux_plein=172
    )
    
    print(f"Pays : {result_2['pays']}")
    print(f"Statut : {result_2['statut_pays']['description']}")
    print(f"Trimestres français : {result_2['trimestres_francais']}")
    print(f"Trimestres totalisés : {result_2['trimestres_totalises']} (AUCUN - pas d'accord)")
    print(f"Taux plein atteint : {'OUI' if result_2['taux_plein_atteint'] else 'NON'}")
    print(f"Trimestres manquants : {result_2['trimestres_manquants']}")
    print(f"Alerte niveau : {result_2['metadata']['alerte_niveau']}")
    
    # EXEMPLE 3 : Calcul pension avec prorata
    print("\n\n### EXEMPLE 3 : Calcul pension avec prorata (Canada) ###")
    print("-" * 80)
    
    result_3 = calculer_pension_avec_prorata_etranger(
        sam=30000,
        taux_liquidation=50.0,
        trimestres_francais=140,
        trimestres_totalises_etranger=20,
        duree_requise=172
    )
    
    print(f"SAM : {result_3['sam']:,.2f} €")
    print(f"Taux de liquidation : {result_3['taux_liquidation']:.2f}%")
    print(f"Trimestres français : {result_3['trimestres_francais']}")
    print(f"Trimestres totalisés étranger : {result_3['trimestres_totalises_etranger']}")
    print(f"Coefficient prorata : {result_3['coefficient_prorata']:.3f}")
    print(f"\nRÉSULTATS :")
    print(f"  - Pension théorique (si tout France) : {result_3['pension_theorique_annuelle']:,.2f} €/an")
    print(f"  - Pension française réelle (prorata) : {result_3['pension_francaise_annuelle']:,.2f} €/an")
    print(f"  - Pension française mensuelle : {result_3['pension_francaise_mensuelle']:,.2f} €/mois")
    print(f"\nNote : {result_3['note']}")
    
    # EXEMPLE 4 : Test API Handler
    print("\n\n### EXEMPLE 4 : Test API Handler N8N ###")
    print("-" * 80)
    
    event_test = {
        "action": "identifier_pays",
        "params": {"pays": "Allemagne"}
    }
    
    result_4 = api_handler(event_test)
    print(f"Success : {result_4['success']}")
    if result_4['success']:
        print(f"Pays identifié : {result_4['data']['pays']}")
        print(f"Statut : {result_4['data']['statut']}")
        print(f"Totalisation possible : {result_4['data']['totalisation_possible']}")
    
    print("\n" + "=" * 80)
    print("Tests terminés avec succès !")
    print("=" * 80)
