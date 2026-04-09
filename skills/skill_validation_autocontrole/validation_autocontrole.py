"""
SYSTÃˆME D'AUTOCONTRÃ”LE ZERO ERREUR - CONSULTATION RETRAITE
==========================================================

Architecture inspirée des systèmes critiques (aviation, médical, finance)
Principe : "Fail-safe by design" = Impossible d'avancer sans validation complète

Créé suite Ã  l'erreur : Attribution de trimestres enfants Ã  un homme
"""

from dataclasses import dataclass
from typing import Dict, List, Tuple, Optional, Any
from datetime import datetime, date
from enum import Enum

# ============================================================================
# NIVEAU 1 : DONNÉES OBLIGATOIRES (Gate #1)
# ============================================================================

class Sexe(Enum):
    HOMME = "H"
    FEMME = "F"

class StatutMarital(Enum):
    CELIBATAIRE = "celibataire"
    MARIE = "marie"
    PACSE = "pacse"
    DIVORCE = "divorce"
    VEUF = "veuf"

@dataclass
class DonneesObligatoires:
    """
    Données qui DOIVENT être collectées avant tout calcul
    Si UNE SEULE est manquante â†’ BLOCAGE
    """
    # Identité
    nom: Optional[str] = None
    prenom: Optional[str] = None
    sexe: Optional[Sexe] = None
    date_naissance: Optional[date] = None
    
    # Situation familiale
    statut_marital: Optional[StatutMarital] = None
    nombre_enfants: Optional[int] = None
    enfants_nes_avant_2010: Optional[bool] = None  # Pour majorations
    
    # Carrière
    date_debut_carriere: Optional[date] = None
    releve_carriere_disponible: bool = False
    
    # Objectif consultation
    type_demande: Optional[str] = None  # "estimation", "rachat", "carriere_longue"


def valider_donnees_obligatoires(donnees: DonneesObligatoires) -> Tuple[bool, List[str]]:
    """
    GATE #1 : Validation des données obligatoires
    
    Returns:
        (validation_ok, liste_champs_manquants)
    """
    manquants = []
    
    # Vérification identité
    if not donnees.nom:
        manquants.append("âŒ Identité.nom")
    if not donnees.prenom:
        manquants.append("âŒ Identité.prenom")
    if not donnees.sexe:
        manquants.append("âŒ Identité.sexe âš ï¸ CRITIQUE (nécessaire pour majorations)")
    if not donnees.date_naissance:
        manquants.append("âŒ Identité.date_naissance")
    
    # Vérification situation familiale
    if donnees.nombre_enfants is None:
        manquants.append("âŒ Situation.nombre_enfants")
    if donnees.nombre_enfants and donnees.nombre_enfants > 0:
        if donnees.enfants_nes_avant_2010 is None:
            manquants.append("âŒ Situation.enfants_nes_avant_2010 (nécessaire pour majorations)")
    
    # Vérification carrière
    if not donnees.date_debut_carriere:
        manquants.append("âŒ Carrière.date_debut_carriere")
    if not donnees.releve_carriere_disponible:
        manquants.append("âŒ Carrière.releve_carriere_disponible")
    
    # Vérification objectif
    if not donnees.type_demande:
        manquants.append("âŒ Objectif.type_demande")
    
    return (len(manquants) == 0, manquants)


# ============================================================================
# NIVEAU 2 : RÃˆGLES DE COHÉRENCE (Gate #2)
# ============================================================================

@dataclass
class RegleCoherence:
    """Représente une règle de cohérence métier"""
    nom: str
    condition: callable  # Fonction qui retourne True si ERREUR détectée
    message_erreur: str
    niveau_gravite: str  # "CRITIQUE" ou "AVERTISSEMENT"


# ============================================================================
# CONSTANTES RÉGLEMENTAIRES — Gate #2
# ============================================================================

# HISTORIQUE DES PLAFONDS SS (PASS) par année — source : CNAV
# RÈGLE ABSOLUE : utiliser TOUJOURS le PASS de l'ANNÉE N, jamais le PASS actuel
# ⚠️ Valeurs pré-2002 = francs convertis en euros (÷ 6.55957) — approximations
# ⚠️ Valeurs post-2002 = euros officiels CNAV — vérifier annuellement
HISTORIQUE_PASS: Dict[int, float] = {
    # --- Francs → Euros (approximations, à vérifier sur relevé CNAV) ---
    1960: 2_440,  1961: 2_745,  1962: 3_050,  1963: 3_355,  1964: 3_660,
    1965: 3_965,  1966: 4_270,  1967: 4_575,  1968: 4_880,  1969: 5_490,
    1970: 5_947,  1971: 6_708,  1972: 7_622,  1973: 8_688,  1974: 9_908,
    1975: 11_280, 1976: 12_804, 1977: 14_480, 1978: 15_850, 1979: 17_374,
    1980: 19_360, 1981: 22_560, 1982: 25_608, 1983: 28_960, 1984: 31_200,
    1985: 6_924,  1986: 7_143,  1987: 7_299,  1988: 7_451,  1989: 7_675,
    1990: 7_946,  1991: 8_217,  1992: 8_496,  1993: 8_714,  1994: 8_868,
    1995: 8_992,  1996: 9_154,  1997: 9_284,  1998: 9_427,  1999: 9_573,
    2000: 9_877,  2001: 10_182,
    # --- Euros officiels CNAV ---
    2002: 26_520, 2003: 27_108, 2004: 27_744, 2005: 30_840,
    2006: 32_184,  # ✅ Confirmé — archives EOR / dossier Kreft-Boussaha déc.2025
    2007: 32_184, 2008: 33_276, 2009: 34_308, 2010: 34_620,
    2011: 35_352, 2012: 36_372, 2013: 37_032, 2014: 37_548,
    2015: 38_040, 2016: 38_616, 2017: 39_228, 2018: 39_732,
    2019: 40_524, 2020: 41_136, 2021: 41_136, 2022: 41_136,
    2023: 43_992,
    2024: 46_368,  # ✅ Confirmé — archives EOR / dossier Kreft-Boussaha déc.2025
    2025: 47_100,  # ⚠️ À confirmer officiellement
    2026: 47_100,  # ⚠️ À confirmer officiellement
}

# Prix d'achat du point AGIRC-ARRCO 2025 — source : AGIRC-ARRCO officiel
# Toute projection future doit utiliser cette valeur — voir Gate #2 R009
PRIX_ACHAT_POINT_AA_2025: float = 20.1877


REGLES_COHERENCE = [
    # RÈGLE 1 : Trimestres enfants uniquement pour les femmes
    RegleCoherence(
        nom="trimestres_enfants_femmes_uniquement",
        condition=lambda d: (
            d.get("sexe") == Sexe.HOMME and 
            d.get("trimestres_majoration_enfants", 0) > 0
        ),
        message_erreur="âŒ INCOHÉRENCE CRITIQUE : Trimestres pour enfants attribués Ã  un homme",
        niveau_gravite="CRITIQUE"
    ),
    
    # RÃˆGLE 2 : Âge légal cohérent
    RegleCoherence(
        nom="age_legal_minimum",
        condition=lambda d: (
            d.get("age_legal_ans") and 
            d.get("age_legal_ans") < 62
        ),
        message_erreur="âŒ INCOHÉRENCE CRITIQUE : Âge légal < 62 ans impossible depuis réforme 2023",
        niveau_gravite="CRITIQUE"
    ),
    
    # RÃˆGLE 3 : Trimestres maximum cohérent
    RegleCoherence(
        nom="trimestres_maximum_carriere",
        condition=lambda d: d.get("trimestres_valides_total", 0) > 200,
        message_erreur="âŒ INCOHÉRENCE CRITIQUE : Plus de 200 trimestres impossible (50 ans de carrière max)",
        niveau_gravite="CRITIQUE"
    ),
    
    # RÃˆGLE 4 : Dates enfants cohérentes
    RegleCoherence(
        nom="dates_enfants_coherentes",
        condition=lambda d: (
            d.get("date_naissance") and 
            d.get("dates_naissance_enfants") and
            any(date_enfant < d["date_naissance"] for date_enfant in d["dates_naissance_enfants"])
        ),
        message_erreur="âŒ INCOHÉRENCE CRITIQUE : Enfant né avant la naissance du client",
        niveau_gravite="CRITIQUE"
    ),
    
    # RÃˆGLE 5 : Majorations cohérentes avec le nombre d'enfants
    RegleCoherence(
        nom="majorations_coherentes_nombre_enfants",
        condition=lambda d: (
            d.get("nombre_enfants", 0) > 0 and
            d.get("sexe") == Sexe.FEMME and
            d.get("trimestres_majoration_enfants", 0) == 0
        ),
        message_erreur="âš ï¸ AVERTISSEMENT : Femme avec enfants mais aucune majoration calculée",
        niveau_gravite="AVERTISSEMENT"
    ),
    
    # RÃˆGLE 6 : Âge taux plein cohérent
    RegleCoherence(
        nom="age_taux_plein_coherent",
        condition=lambda d: (
            d.get("age_taux_plein_ans") and
            d.get("age_taux_plein_ans") > 67
        ),
        message_erreur="âŒ INCOHÉRENCE CRITIQUE : Âge taux plein automatique > 67 ans impossible",
        niveau_gravite="CRITIQUE"
    ),
    
    # RÃˆGLE 7 : Salaire de référence cohérent
    RegleCoherence(
        nom="salaire_reference_coherent",
        condition=lambda d: (
            d.get("salaire_annuel_moyen") and
            d.get("salaire_annuel_moyen") > 200000  # > 4x PASS
        ),
        message_erreur="âš ï¸ AVERTISSEMENT : SAM > 200kâ‚¬ (vérifier plafonnement)",
        niveau_gravite="AVERTISSEMENT"
    ),
    
    # RÃˆGLE 8 : Carrière longue cohérente
    RegleCoherence(
        nom="carriere_longue_ages_coherents",
        condition=lambda d: (
            d.get("eligible_carriere_longue") and
            d.get("age_depart_carriere_longue_ans", 0) < 58
        ),
        message_erreur="âš ï¸ AVERTISSEMENT : Départ carrière longue < 58 ans (cas très rare)",
        niveau_gravite="AVERTISSEMENT"
    ),

    # -------------------------------------------------------------------------
    # R005 | Anachronisme PASS — plafond SS historique incorrect
    # Date : 06/04/2026 — Origine : dossier Kreft/Boussaha déc.2025
    # Ref : REGISTRE_ERREURS_COHERENCE.md → R005
    # -------------------------------------------------------------------------
    RegleCoherence(
        nom="anachronisme_pass_historique",
        condition=lambda d: (
            bool(d.get("historique_salaires")) and
            any(
                s.get("annee") in HISTORIQUE_PASS
                and s.get("pass_utilise") is not None
                and abs(s["pass_utilise"] - HISTORIQUE_PASS[s["annee"]]) > 1
                for s in d["historique_salaires"]
            )
        ),
        message_erreur=(
            "❌ ERREUR CRITIQUE : Le plafond SS utilisé pour au moins une année de carrière "
            "ne correspond pas au PASS historique de cette année. "
            "Ne jamais appliquer le PASS actuel sur des années antérieures. "
            "Règle : S_Retenu = Min(S_Brut, HISTORIQUE_PASS[annee]) avant revalorisation."
        ),
        niveau_gravite="CRITIQUE"
    ),

    # -------------------------------------------------------------------------
    # R006 | Oubli 'Bouclier 67 ans' — arbitrage décote CNAV (Art. R351-27 CSS)
    # Date : 06/04/2026 — Origine : dossier Kreft/Boussaha déc.2025
    # Ref : REGISTRE_ERREURS_COHERENCE.md → R006
    # -------------------------------------------------------------------------
    RegleCoherence(
        nom="bouclier_67_ans_decote",
        condition=lambda d: (
            d.get("nb_trim_decote") is not None
            and d.get("manquants_duree") is not None
            and d.get("age_depart_ans") is not None
            and d["nb_trim_decote"] != min(
                d["manquants_duree"],
                (67 - d["age_depart_ans"]) * 4
            )
        ),
        message_erreur=(
            "❌ ERREUR CRITIQUE : Décote calculée sans arbitrage 'Bouclier 67 ans' "
            "(Art. R351-27 CSS). La loi impose : "
            "nb_trim_decote = Min(manquants_durée, (67 - âge_départ) × 4). "
            "Résultat actuel potentiellement trop pénalisant."
        ),
        niveau_gravite="CRITIQUE"
    ),

    # -------------------------------------------------------------------------
    # R007 | Décote CNAV et AGIRC-ARRCO non synchronisées
    # Date : 06/04/2026 — Origine : dossier Kreft/Boussaha déc.2025
    # Le même nb de trimestres (post-arbitrage R006) s'applique aux 2 régimes
    # Ref : REGISTRE_ERREURS_COHERENCE.md → R007
    # -------------------------------------------------------------------------
    RegleCoherence(
        nom="synchro_decote_cnav_agirc_arrco",
        condition=lambda d: (
            d.get("trim_decote_cnav") is not None
            and d.get("trim_decote_agirc_arrco") is not None
            and d["trim_decote_cnav"] != d["trim_decote_agirc_arrco"]
        ),
        message_erreur=(
            "❌ ERREUR CRITIQUE : Le nombre de trimestres manquants appliqué à la décote CNAV "
            "diffère de celui utilisé pour le coefficient AGIRC-ARRCO. "
            "Ces deux valeurs doivent être strictement identiques "
            "(toutes deux issues de l'arbitrage Bouclier 67 ans — R006)."
        ),
        niveau_gravite="CRITIQUE"
    ),

    # -------------------------------------------------------------------------
    # R008 | Dilution SAM — années futures sous-performantes dans Top 25
    # Date : 06/04/2026 — Origine : dossier Kreft/Boussaha déc.2025
    # Algo correct : Top25 = sorted([histo_reval] + [futur], reverse=True)[:25]
    # Ref : REGISTRE_ERREURS_COHERENCE.md → R008
    # -------------------------------------------------------------------------
    RegleCoherence(
        nom="dilution_sam_annees_futures",
        condition=lambda d: (
            d.get("revenus_futurs_dans_sam") is not None
            and d.get("seuil_sam_25e_rang") is not None
            and any(
                r < d["seuil_sam_25e_rang"]
                for r in d["revenus_futurs_dans_sam"]
                if r > 0
            )
        ),
        message_erreur=(
            "❌ ERREUR CRITIQUE : Une ou plusieurs années futures incluses dans le SAM "
            "sont inférieures à la 25e meilleure année historique revalorisée "
            "(seuil = seuil_sam_25e_rang). "
            "Ces années doivent être exclues du SAM — elles comptent pour la durée, "
            "pas pour le montant. "
            "Appliquer : Top25 = sorted([histo_reval] + [futur], reverse=True)[:25]"
        ),
        niveau_gravite="CRITIQUE"
    ),

    # -------------------------------------------------------------------------
    # R009 | Prix d'achat du point AGIRC-ARRCO non contemporain
    # Date : 06/04/2026 — Origine : dossier Kreft/Boussaha déc.2025
    # Valeur officielle 2025 : 20.1877 € — source AGIRC-ARRCO
    # Ref : REGISTRE_ERREURS_COHERENCE.md → R009
    # -------------------------------------------------------------------------
    RegleCoherence(
        nom="prix_achat_point_agirc_arrco_2025",
        condition=lambda d: (
            d.get("prix_achat_point_agirc_arrco") is not None
            and abs(d["prix_achat_point_agirc_arrco"] - PRIX_ACHAT_POINT_AA_2025) > 0.01
        ),
        message_erreur=(
            f"❌ ERREUR CRITIQUE : Le prix d'achat du point AGIRC-ARRCO utilisé "
            f"ne correspond pas à la valeur officielle 2025 ({PRIX_ACHAT_POINT_AA_2025} €). "
            f"Toute projection future doit utiliser "
            f"PRIX_ACHAT_POINT_AA_2025 = {PRIX_ACHAT_POINT_AA_2025} €."
        ),
        niveau_gravite="CRITIQUE"
    ),

]


def valider_coherence_donnees(donnees: Dict[str, Any]) -> Tuple[bool, List[str], List[str]]:
    """
    GATE #2 : Validation de la cohérence logique
    
    Returns:
        (validation_ok, erreurs_critiques, avertissements)
    """
    erreurs_critiques = []
    avertissements = []
    
    for regle in REGLES_COHERENCE:
        try:
            if regle.condition(donnees):
                if regle.niveau_gravite == "CRITIQUE":
                    erreurs_critiques.append(f"{regle.message_erreur} [Règle: {regle.nom}]")
                else:
                    avertissements.append(f"{regle.message_erreur} [Règle: {regle.nom}]")
        except KeyError as e:
            # Donnée manquante pour vérifier la règle
            avertissements.append(f"âš ï¸ Impossible de vérifier règle '{regle.nom}' : donnée manquante {e}")
        except Exception as e:
            avertissements.append(f"âš ï¸ Erreur lors de la vérification de '{regle.nom}' : {e}")
    
    return (len(erreurs_critiques) == 0, erreurs_critiques, avertissements)


# ============================================================================
# NIVEAU 3 : VALIDATION MÉTIER (Gate #3)
# ============================================================================

def valider_calculs_retraite(donnees: Dict[str, Any]) -> Tuple[bool, List[str]]:
    """
    GATE #3 : Validation que tous les calculs métier obligatoires sont présents
    
    Returns:
        (validation_ok, liste_erreurs)
    """
    erreurs = []
    
    # 1. Âges clés DOIVENT être calculés
    ages_obligatoires = ["age_legal", "age_taux_plein", "date_67_ans"]
    for age in ages_obligatoires:
        if age not in donnees or not donnees[age]:
            erreurs.append(f"âŒ CALCUL MANQUANT : {age}")
    
    # 2. Trimestres DOIVENT être analysés
    if "trimestres_valides_total" not in donnees:
        erreurs.append("âŒ CALCUL MANQUANT : trimestres_valides_total")
    
    # 3. Scénarios DOIVENT être calculés (minimum 3)
    scenarios = donnees.get("scenarios", [])
    if len(scenarios) < 3:
        erreurs.append(f"âŒ CALCUL INCOMPLET : {len(scenarios)} scénarios au lieu de 3 minimum")
    
    # Vérifier présence des 3 scénarios clés
    types_scenarios = [s.get("type") for s in scenarios]
    scenarios_requis = ["age_legal", "taux_plein", "67_ans"]
    for scenario_type in scenarios_requis:
        if scenario_type not in types_scenarios:
            erreurs.append(f"âŒ SCÉNARIO MANQUANT : {scenario_type}")
    
    # 4. Pensions DOIVENT être estimées pour chaque scénario
    for idx, scenario in enumerate(scenarios):
        if "pension_base_mensuelle" not in scenario:
            erreurs.append(f"âŒ PENSION MANQUANTE : scénario #{idx+1} - pension base")
        if "pension_complementaire_mensuelle" not in scenario:
            erreurs.append(f"âŒ PENSION MANQUANTE : scénario #{idx+1} - pension complémentaire")
    
    # 5. Si carrière longue évoquée â†’ éligibilité DOIT être vérifiée
    if donnees.get("demande_carriere_longue"):
        if "eligible_carriere_longue" not in donnees:
            erreurs.append("âŒ VÉRIFICATION MANQUANTE : éligibilité carrière longue")
    
    # 6. Si rachat évoqué â†’ simulation DOIT être faite
    if donnees.get("demande_rachat_trimestres"):
        if "simulation_rachat" not in donnees:
            erreurs.append("âŒ SIMULATION MANQUANTE : rachat de trimestres")
    
    return (len(erreurs) == 0, erreurs)


# ============================================================================
# NIVEAU 4 : AUTO-VALIDATION POST-GÉNÉRATION (Gate #4)
# ============================================================================

def valider_document_word_genere(chemin_document: str) -> Tuple[bool, List[str]]:
    """
    GATE #4 : Validation du document Word généré
    
    Vérifie que le document contient tous les éléments obligatoires
    
    Returns:
        (validation_ok, liste_erreurs)
    """
    try:
        from docx import Document
        import os
        
        if not os.path.exists(chemin_document):
            return (False, ["âŒ ERREUR CRITIQUE : Document Word non créé"])
        
        doc = Document(chemin_document)
        texte_complet = "\n".join([p.text.lower() for p in doc.paragraphs])
        
        erreurs = []
        
        # 1. Vérifier présence âge légal
        if "âge légal" not in texte_complet and "age legal" not in texte_complet:
            erreurs.append("âŒ CONTENU MANQUANT : Âge légal non mentionné")
        
        # 2. Vérifier présence des 3 scénarios
        nb_scenarios = texte_complet.count("scénario") + texte_complet.count("scenario")
        if nb_scenarios < 3:
            erreurs.append(f"âŒ CONTENU INCOMPLET : Moins de 3 scénarios ({nb_scenarios} détectés)")
        
        # 3. Vérifier présence tableau comparatif
        if len(doc.tables) < 1:
            erreurs.append("âŒ CONTENU MANQUANT : Aucun tableau de comparaison")
        
        # 4. Vérifier mentions légales obligatoires
        mentions_obligatoires = [
            "caractère indicatif",
            "montants bruts",
            "seules les caisses de retraite"
        ]
        for mention in mentions_obligatoires:
            if mention.lower() not in texte_complet:
                erreurs.append(f"âš ï¸ MENTION LÉGALE MANQUANTE : {mention}")
        
        # 5. Vérifier absence de jargon technique excessif
        jargon_a_eviter = [
            "coefficient de proratisation",
            "durée d'assurance tous régimes confondus",
            "décote/surcote"  # préférer "minoration/majoration"
        ]
        for terme in jargon_a_eviter:
            if terme.lower() in texte_complet:
                erreurs.append(f"âš ï¸ JARGON DÉTECTÉ (Ã  éviter pour client) : {terme}")
        
        # 6. Vérifier que le document n'est pas vide
        if len(texte_complet.strip()) < 500:
            erreurs.append("âŒ ERREUR CRITIQUE : Document quasiment vide")
        
        return (len(erreurs) == 0, erreurs)
    
    except Exception as e:
        return (False, [f"âŒ ERREUR TECHNIQUE : Impossible de valider le document - {e}"])


# ============================================================================
# ORCHESTRATION : VALIDATION COMPLÃˆTE EN CASCADE
# ============================================================================

def executer_validation_complete(
    donnees_base: DonneesObligatoires,
    donnees_calculs: Dict[str, Any],
    chemin_document_final: Optional[str] = None
) -> Tuple[bool, Dict[str, List[str]]]:
    """
    Exécute la validation complète en cascade (4 niveaux)
    
    S'arrête au premier niveau qui échoue
    
    Returns:
        (validation_globale_ok, {
            "niveau_1": [...erreurs],
            "niveau_2_critiques": [...],
            "niveau_2_avertissements": [...],
            "niveau_3": [...],
            "niveau_4": [...]
        })
    """
    resultats = {
        "niveau_1": [],
        "niveau_2_critiques": [],
        "niveau_2_avertissements": [],
        "niveau_3": [],
        "niveau_4": []
    }
    
    # GATE #1 : Données obligatoires
    print("ðŸ” GATE #1 : Validation données obligatoires...")
    ok_niveau1, erreurs_n1 = valider_donnees_obligatoires(donnees_base)
    resultats["niveau_1"] = erreurs_n1
    
    if not ok_niveau1:
        print("âŒ VALIDATION ÉCHOUÉE AU NIVEAU 1")
        print("â›” BLOCAGE : Impossible de continuer sans les données obligatoires")
        return (False, resultats)
    
    print("âœ… GATE #1 : OK - Toutes les données obligatoires présentes")
    
    # GATE #2 : Cohérence logique
    print("\nðŸ” GATE #2 : Validation cohérence logique...")
    ok_niveau2, critiques_n2, avert_n2 = valider_coherence_donnees(donnees_calculs)
    resultats["niveau_2_critiques"] = critiques_n2
    resultats["niveau_2_avertissements"] = avert_n2
    
    if not ok_niveau2:
        print("âŒ VALIDATION ÉCHOUÉE AU NIVEAU 2")
        print("â›” BLOCAGE : Incohérences critiques détectées")
        return (False, resultats)
    
    if avert_n2:
        print("âš ï¸ GATE #2 : Avertissements détectés (non bloquants)")
    else:
        print("âœ… GATE #2 : OK - Cohérence logique validée")
    
    # GATE #3 : Calculs métier
    print("\nðŸ” GATE #3 : Validation calculs métier...")
    ok_niveau3, erreurs_n3 = valider_calculs_retraite(donnees_calculs)
    resultats["niveau_3"] = erreurs_n3
    
    if not ok_niveau3:
        print("âŒ VALIDATION ÉCHOUÉE AU NIVEAU 3")
        print("â›” BLOCAGE : Calculs métier incomplets")
        return (False, resultats)
    
    print("âœ… GATE #3 : OK - Tous les calculs métier validés")
    
    # GATE #4 : Document généré (optionnel)
    if chemin_document_final:
        print("\nðŸ” GATE #4 : Validation document Word généré...")
        ok_niveau4, erreurs_n4 = valider_document_word_genere(chemin_document_final)
        resultats["niveau_4"] = erreurs_n4
        
        if not ok_niveau4:
            print("âŒ VALIDATION ÉCHOUÉE AU NIVEAU 4")
            print("â›” BLOCAGE : Document incomplet ou incorrect")
            return (False, resultats)
        
        print("âœ… GATE #4 : OK - Document Word valide")
    
    print("\n" + "="*60)
    print("âœ… VALIDATION COMPLÃˆTE RÉUSSIE - Tous les niveaux passés")
    print("="*60)
    
    return (True, resultats)


# ============================================================================
# HELPER : AFFICHAGE CHECKLIST POUR CLAUDE
# ============================================================================

def afficher_checklist_validation(donnees_base: DonneesObligatoires) -> str:
    """
    Génère une checklist visuelle pour Claude
    À afficher AVANT de générer le document
    """
    checklist = """
â•”â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•—
â•‘         CHECKLIST VALIDATION PRÉ-GÉNÉRATION DOCUMENT       â•‘
â•šâ•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

ðŸ“‹ DONNÉES OBLIGATOIRES :
"""
    
    # Identité
    checklist += f"  {'âœ…' if donnees_base.nom else 'âŒ'} Nom : {donnees_base.nom or 'MANQUANT'}\n"
    checklist += f"  {'âœ…' if donnees_base.prenom else 'âŒ'} Prénom : {donnees_base.prenom or 'MANQUANT'}\n"
    checklist += f"  {'âœ…' if donnees_base.sexe else 'âŒ'} Sexe : {donnees_base.sexe.value if donnees_base.sexe else 'MANQUANT âš ï¸ CRITIQUE'}\n"
    checklist += f"  {'âœ…' if donnees_base.date_naissance else 'âŒ'} Date naissance : {donnees_base.date_naissance or 'MANQUANT'}\n"
    
    # Situation familiale
    checklist += f"\nðŸ‘¨â€ðŸ‘©â€ðŸ‘§â€ðŸ‘¦ SITUATION FAMILIALE :\n"
    checklist += f"  {'âœ…' if donnees_base.nombre_enfants is not None else 'âŒ'} Nombre enfants : {donnees_base.nombre_enfants if donnees_base.nombre_enfants is not None else 'MANQUANT'}\n"
    
    if donnees_base.nombre_enfants and donnees_base.nombre_enfants > 0:
        checklist += f"  {'âœ…' if donnees_base.enfants_nes_avant_2010 is not None else 'âŒ'} Enfants nés avant 2010 : {donnees_base.enfants_nes_avant_2010 if donnees_base.enfants_nes_avant_2010 is not None else 'MANQUANT'}\n"
    
    # Carrière
    checklist += f"\nðŸ’¼ CARRIÃˆRE :\n"
    checklist += f"  {'âœ…' if donnees_base.date_debut_carriere else 'âŒ'} Date début carrière : {donnees_base.date_debut_carriere or 'MANQUANT'}\n"
    checklist += f"  {'âœ…' if donnees_base.releve_carriere_disponible else 'âŒ'} Relevé carrière disponible : {donnees_base.releve_carriere_disponible}\n"
    
    # Objectif
    checklist += f"\nðŸŽ¯ OBJECTIF :\n"
    checklist += f"  {'âœ…' if donnees_base.type_demande else 'âŒ'} Type demande : {donnees_base.type_demande or 'MANQUANT'}\n"
    
    # Verdict
    ok, manquants = valider_donnees_obligatoires(donnees_base)
    
    if ok:
        checklist += "\n" + "="*60 + "\n"
        checklist += "âœ… CHECKLIST COMPLÃˆTE - Génération du document autorisée\n"
        checklist += "="*60 + "\n"
    else:
        checklist += "\n" + "="*60 + "\n"
        checklist += "âŒ CHECKLIST INCOMPLÃˆTE - Génération BLOQUÉE\n"
        checklist += "â›” Données manquantes :\n"
        for manquant in manquants:
            checklist += f"   {manquant}\n"
        checklist += "="*60 + "\n"
    
    return checklist


# ============================================================================
# EXEMPLE D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    # Exemple 1 : CAS D'ERREUR (homme avec trimestres enfants)
    print("="*60)
    print("TEST 1 : Homme avec trimestres enfants (DOIT ÉCHOUER)")
    print("="*60)
    
    donnees_base_erreur = DonneesObligatoires(
        nom="Dupont",
        prenom="Jean",
        sexe=Sexe.HOMME,  # Homme
        date_naissance=date(1960, 5, 15),
        nombre_enfants=2,
        enfants_nes_avant_2010=True,
        date_debut_carriere=date(1980, 1, 1),
        releve_carriere_disponible=True,
        type_demande="estimation"
    )
    
    donnees_calculs_erreur = {
        "sexe": Sexe.HOMME,
        "trimestres_majoration_enfants": 8,  # âŒ ERREUR : homme avec trimestres enfants
        "age_legal": date(2027, 5, 15),
        "age_taux_plein": date(2029, 5, 15),
        "date_67_ans": date(2027, 5, 15),
        "trimestres_valides_total": 168,
        "scenarios": [
            {"type": "age_legal", "pension_base_mensuelle": 1500},
            {"type": "taux_plein", "pension_base_mensuelle": 1650},
            {"type": "67_ans", "pension_base_mensuelle": 1650}
        ]
    }
    
    print("\n" + afficher_checklist_validation(donnees_base_erreur))
    
    validation_ok, resultats = executer_validation_complete(
        donnees_base_erreur,
        donnees_calculs_erreur
    )
    
    if not validation_ok:
        print("\nðŸ”´ VALIDATION ÉCHOUÉE (comme attendu)")
        if resultats["niveau_2_critiques"]:
            print("\nâŒ ERREURS CRITIQUES DÉTECTÉES :")
            for erreur in resultats["niveau_2_critiques"]:
                print(f"   {erreur}")
    
    print("\n" + "="*60 + "\n")
    
    # Exemple 2 : CAS CORRECT (femme avec trimestres enfants)
    print("="*60)
    print("TEST 2 : Femme avec trimestres enfants (DOIT RÉUSSIR)")
    print("="*60)
    
    donnees_base_ok = DonneesObligatoires(
        nom="Martin",
        prenom="Sophie",
        sexe=Sexe.FEMME,  # Femme
        date_naissance=date(1960, 5, 15),
        nombre_enfants=2,
        enfants_nes_avant_2010=True,
        date_debut_carriere=date(1980, 1, 1),
        releve_carriere_disponible=True,
        type_demande="estimation"
    )
    
    donnees_calculs_ok = {
        "sexe": Sexe.FEMME,
        "trimestres_majoration_enfants": 8,  # âœ… OK : femme avec trimestres enfants
        "age_legal": date(2027, 5, 15),
        "age_legal_ans": 67,
        "age_taux_plein": date(2029, 5, 15),
        "age_taux_plein_ans": 69,
        "date_67_ans": date(2027, 5, 15),
        "trimestres_valides_total": 168,
        "nombre_enfants": 2,
        "date_naissance": date(1960, 5, 15),
        "scenarios": [
            {
                "type": "age_legal",
                "pension_base_mensuelle": 1500,
                "pension_complementaire_mensuelle": 400
            },
            {
                "type": "taux_plein",
                "pension_base_mensuelle": 1650,
                "pension_complementaire_mensuelle": 450
            },
            {
                "type": "67_ans",
                "pension_base_mensuelle": 1650,
                "pension_complementaire_mensuelle": 450
            }
        ]
    }
    
    print("\n" + afficher_checklist_validation(donnees_base_ok))
    
    validation_ok, resultats = executer_validation_complete(
        donnees_base_ok,
        donnees_calculs_ok
    )
    
    if validation_ok:
        print("\nðŸŸ¢ VALIDATION RÉUSSIE (comme attendu)")
