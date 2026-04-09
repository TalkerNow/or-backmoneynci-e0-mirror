"""
VALIDATION CONTINUITÉ DE CARRIÃˆRE - DÉTECTION EXHAUSTIVE DES TROUS
===================================================================

Problème : Claude détecte CERTAINS trous (ex: 2012, 2018) mais en rate D'AUTRES (ex: 2000-2001)

Solution : Analyse année par année OBLIGATOIRE avec timeline visuelle

Créé pour résoudre l'erreur : "8 trimestres manquants entre 1999 et 2002 non signalés"
"""

from dataclasses import dataclass
from typing import List, Dict, Tuple, Optional
from datetime import date
from enum import Enum

# ============================================================================
# STRUCTURES DE DONNÉES
# ============================================================================

@dataclass
class AnneeCarriere:
    """Représente une année de carrière"""
    annee: int
    trimestres_cotises: int
    trimestres_assimiles: int
    trimestres_total: int
    salaire: Optional[float] = None
    regime: Optional[str] = None  # "CNAV", "AGIRC-ARRCO", etc.
    statut: Optional[str] = None  # "Salarié", "Indépendant", etc.
    
    @property
    def est_complete(self) -> bool:
        """Une année complète = 4 trimestres"""
        return self.trimestres_total >= 4
    
    @property
    def est_vide(self) -> bool:
        """Année sans aucun trimestre"""
        return self.trimestres_total == 0
    
    @property
    def est_incomplete(self) -> bool:
        """Année avec 1-3 trimestres seulement"""
        return 0 < self.trimestres_total < 4


@dataclass
class TrouCarriere:
    """Représente un trou détecté dans la carrière"""
    annee_debut: int
    annee_fin: int
    nb_annees: int
    nb_trimestres_manquants: int
    type_trou: str  # "COMPLET" (0 trim) ou "PARTIEL" (1-3 trim)
    gravite: str  # "CRITIQUE", "IMPORTANT", "MINEUR"
    
    @property
    def description(self) -> str:
        if self.nb_annees == 1:
            return f"Année {self.annee_debut} : {self.nb_trimestres_manquants} trimestre(s) manquant(s)"
        else:
            return f"Années {self.annee_debut}-{self.annee_fin} : {self.nb_trimestres_manquants} trimestres manquants sur {self.nb_annees} ans"


# ============================================================================
# ANALYSEUR DE CONTINUITÉ
# ============================================================================

class AnalyseurContinuite:
    """
    Analyse EXHAUSTIVE de la continuité de carrière
    Détecte TOUS les trous, sans exception
    """
    
    def __init__(self, annee_debut_carriere: int, annee_actuelle: int):
        self.annee_debut = annee_debut_carriere
        self.annee_fin = annee_actuelle
        self.annees_carriere: Dict[int, AnneeCarriere] = {}
        self.trous_detectes: List[TrouCarriere] = []
    
    def ajouter_annee(self, annee: AnneeCarriere):
        """Ajoute une année de carrière"""
        self.annees_carriere[annee.annee] = annee
    
    def analyser_continuite(self) -> Tuple[bool, List[TrouCarriere], str]:
        """
        Analyse EXHAUSTIVE année par année
        
        Returns:
            (continuite_ok, liste_trous, rapport_visuel)
        """
        self.trous_detectes = []
        
        # Parcourir CHAQUE ANNÉE de la carrière
        for annee in range(self.annee_debut, self.annee_fin + 1):
            
            # Si l'année n'est pas dans les données â†’ TROU COMPLET
            if annee not in self.annees_carriere:
                self._ajouter_trou_complet(annee)
            
            # Si l'année existe mais est incomplète â†’ TROU PARTIEL
            elif self.annees_carriere[annee].est_incomplete:
                self._ajouter_trou_partiel(annee)
            
            # Si l'année est vide (0 trimestres) â†’ TROU COMPLET
            elif self.annees_carriere[annee].est_vide:
                self._ajouter_trou_complet(annee)
        
        # Fusionner les trous consécutifs
        self._fusionner_trous_consecutifs()
        
        # Évaluer la gravité
        self._evaluer_gravite_trous()
        
        # Générer le rapport visuel
        rapport = self._generer_rapport_visuel()
        
        # Continuité OK si aucun trou critique ou important
        continuite_ok = not any(
            t.gravite in ["CRITIQUE", "IMPORTANT"] 
            for t in self.trous_detectes
        )
        
        return (continuite_ok, self.trous_detectes, rapport)
    
    def _ajouter_trou_complet(self, annee: int):
        """Ajoute un trou complet (0 trimestres)"""
        trou = TrouCarriere(
            annee_debut=annee,
            annee_fin=annee,
            nb_annees=1,
            nb_trimestres_manquants=4,
            type_trou="COMPLET",
            gravite="CRITIQUE"  # Sera réévalué après
        )
        self.trous_detectes.append(trou)
    
    def _ajouter_trou_partiel(self, annee: int):
        """Ajoute un trou partiel (1-3 trimestres)"""
        annee_data = self.annees_carriere[annee]
        trimestres_manquants = 4 - annee_data.trimestres_total
        
        trou = TrouCarriere(
            annee_debut=annee,
            annee_fin=annee,
            nb_annees=1,
            nb_trimestres_manquants=trimestres_manquants,
            type_trou="PARTIEL",
            gravite="IMPORTANT"  # Sera réévalué après
        )
        self.trous_detectes.append(trou)
    
    def _fusionner_trous_consecutifs(self):
        """Fusionne les trous sur années consécutives"""
        if not self.trous_detectes:
            return
        
        trous_fusionnes = []
        trou_courant = self.trous_detectes[0]
        
        for i in range(1, len(self.trous_detectes)):
            trou_suivant = self.trous_detectes[i]
            
            # Si consécutifs et même type â†’ fusionner
            if (trou_suivant.annee_debut == trou_courant.annee_fin + 1 and
                trou_suivant.type_trou == trou_courant.type_trou):
                
                trou_courant = TrouCarriere(
                    annee_debut=trou_courant.annee_debut,
                    annee_fin=trou_suivant.annee_fin,
                    nb_annees=trou_courant.nb_annees + trou_suivant.nb_annees,
                    nb_trimestres_manquants=trou_courant.nb_trimestres_manquants + trou_suivant.nb_trimestres_manquants,
                    type_trou=trou_courant.type_trou,
                    gravite=trou_courant.gravite
                )
            else:
                trous_fusionnes.append(trou_courant)
                trou_courant = trou_suivant
        
        trous_fusionnes.append(trou_courant)
        self.trous_detectes = trous_fusionnes
    
    def _evaluer_gravite_trous(self):
        """Évalue la gravité de chaque trou"""
        for trou in self.trous_detectes:
            # CRITIQUE : 2+ années complètes manquantes OU 8+ trimestres
            if trou.nb_trimestres_manquants >= 8 or (trou.type_trou == "COMPLET" and trou.nb_annees >= 2):
                trou.gravite = "CRITIQUE"
            
            # IMPORTANT : 1 année complète OU 4-7 trimestres
            elif trou.nb_trimestres_manquants >= 4:
                trou.gravite = "IMPORTANT"
            
            # MINEUR : 1-3 trimestres seulement
            else:
                trou.gravite = "MINEUR"
    
    def _generer_rapport_visuel(self) -> str:
        """Génère une timeline visuelle de la carrière"""
        rapport = []
        rapport.append("\n" + "="*80)
        rapport.append("TIMELINE DE CARRIÃˆRE (ANALYSE EXHAUSTIVE)")
        rapport.append("="*80)
        rapport.append("")
        
        # Légende
        rapport.append("Légende : â–ˆâ–ˆâ–ˆâ–ˆ Année complète (4 trim.) | â–“â–“â–“â–“ Partielle (1-3 trim.) | â–‘â–‘â–‘â–‘ Vide (0 trim.)")
        rapport.append("")
        
        # Timeline par tranches de 10 ans
        for debut_tranche in range(self.annee_debut, self.annee_fin + 1, 10):
            fin_tranche = min(debut_tranche + 9, self.annee_fin)
            
            ligne_annees = f"{debut_tranche}-{fin_tranche}: "
            ligne_visuelle = "           "
            
            for annee in range(debut_tranche, fin_tranche + 1):
                if annee > self.annee_fin:
                    break
                
                if annee not in self.annees_carriere:
                    ligne_visuelle += "â–‘â–‘â–‘â–‘ "  # Vide
                elif self.annees_carriere[annee].est_complete:
                    ligne_visuelle += "â–ˆâ–ˆâ–ˆâ–ˆ "  # Complète
                elif self.annees_carriere[annee].est_vide:
                    ligne_visuelle += "â–‘â–‘â–‘â–‘ "  # Vide
                else:
                    ligne_visuelle += "â–“â–“â–“â–“ "  # Partielle
            
            rapport.append(ligne_annees + ligne_visuelle)
        
        rapport.append("")
        rapport.append("="*80)
        
        return "\n".join(rapport)


# ============================================================================
# FONCTION DE VALIDATION POUR PROMPT 1
# ============================================================================

def valider_continuite_carriere_prompt1(
    annee_debut: int,
    annee_actuelle: int,
    donnees_releve: Dict[int, Dict]
) -> Tuple[bool, List[str], str]:
    """
    Fonction Ã  appeler dans PROMPT 1 pour valider la continuité
    
    Args:
        annee_debut: Première année de carrière
        annee_actuelle: Année en cours
        donnees_releve: Dictionnaire {annee: {"trimestres": X, "salaire": Y, ...}}
    
    Returns:
        (validation_ok, liste_erreurs, rapport_timeline)
    """
    analyseur = AnalyseurContinuite(annee_debut, annee_actuelle)
    
    # Charger les données
    for annee, data in donnees_releve.items():
        annee_carriere = AnneeCarriere(
            annee=annee,
            trimestres_cotises=data.get("trimestres_cotises", 0),
            trimestres_assimiles=data.get("trimestres_assimiles", 0),
            trimestres_total=data.get("trimestres_total", 0),
            salaire=data.get("salaire"),
            regime=data.get("regime"),
            statut=data.get("statut")
        )
        analyseur.ajouter_annee(annee_carriere)
    
    # Analyser
    continuite_ok, trous, timeline = analyseur.analyser_continuite()
    
    # Formater les erreurs
    erreurs = []
    
    if not continuite_ok:
        erreurs.append("âŒ CONTINUITÉ DE CARRIÃˆRE : Trous détectés")
        erreurs.append("")
        
        # Trier par gravité
        trous_critiques = [t for t in trous if t.gravite == "CRITIQUE"]
        trous_importants = [t for t in trous if t.gravite == "IMPORTANT"]
        trous_mineurs = [t for t in trous if t.gravite == "MINEUR"]
        
        if trous_critiques:
            erreurs.append("ðŸ”´ TROUS CRITIQUES (â‰¥8 trimestres manquants) :")
            for trou in trous_critiques:
                erreurs.append(f"   â€¢ {trou.description}")
            erreurs.append("")
        
        if trous_importants:
            erreurs.append("ðŸŸ  TROUS IMPORTANTS (4-7 trimestres manquants) :")
            for trou in trous_importants:
                erreurs.append(f"   â€¢ {trou.description}")
            erreurs.append("")
        
        if trous_mineurs:
            erreurs.append("ðŸŸ¡ TROUS MINEURS (1-3 trimestres manquants) :")
            for trou in trous_mineurs:
                erreurs.append(f"   â€¢ {trou.description}")
            erreurs.append("")
        
        # Total
        total_trimestres_manquants = sum(t.nb_trimestres_manquants for t in trous)
        erreurs.append(f"ðŸ“Š TOTAL : {total_trimestres_manquants} trimestres manquants sur {annee_actuelle - annee_debut + 1} ans de carrière")
        erreurs.append("")
        erreurs.append("âš ï¸ ACTION REQUISE : Interroger le client sur ces périodes")
    else:
        erreurs.append("âœ… CONTINUITÉ DE CARRIÃˆRE : Aucun trou significatif détecté")
    
    return (continuite_ok, erreurs, timeline)


# ============================================================================
# EXEMPLE D'UTILISATION (CAS RÉEL DE L'ERREUR)
# ============================================================================

if __name__ == "__main__":
    print("="*80)
    print("TEST : DÉTECTION DU TROU 2000-2001 (8 TRIMESTRES MANQUANTS)")
    print("="*80)
    print()
    
    # Simulation du relevé de carrière avec le trou non détecté
    releve_carriere = {
        1998: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        1999: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        # 2000 : MANQUANT âŒ
        # 2001 : MANQUANT âŒ
        2002: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2003: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2004: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2005: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2006: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2007: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2008: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2009: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2010: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2011: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2012: {"trimestres_total": 0, "trimestres_cotises": 0, "trimestres_assimiles": 0},  # Celui que Claude détecte âœ…
        2013: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2014: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2015: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2016: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2017: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2018: {"trimestres_total": 2, "trimestres_cotises": 2, "trimestres_assimiles": 0},  # Celui que Claude détecte âœ…
        2019: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2020: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2021: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2022: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
        2023: {"trimestres_total": 4, "trimestres_cotises": 4, "trimestres_assimiles": 0},
    }
    
    # Analyser avec le nouveau système
    validation_ok, erreurs, timeline = valider_continuite_carriere_prompt1(
        annee_debut=1998,
        annee_actuelle=2023,
        donnees_releve=releve_carriere
    )
    
    # Afficher la timeline
    print(timeline)
    print()
    
    # Afficher les erreurs
    print("\n".join(erreurs))
    print()
    
    # Verdict
    print("="*80)
    if not validation_ok:
        print("âŒ VALIDATION ÉCHOUÉE : Des trous ont été détectés")
        print()
        print("ðŸŽ¯ RÉSULTAT : Le système a détecté le trou 2000-2001 (8 trimestres)")
        print("   que Claude aurait raté dans son analyse manuelle.")
        print()
        print("âœ… Le consultant doit OBLIGATOIREMENT interroger le client sur ces périodes.")
    else:
        print("âœ… VALIDATION RÉUSSIE : Carrière continue")
    print("="*80)
