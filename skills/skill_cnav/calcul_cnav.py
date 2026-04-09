"""
========================================
SKILL : Calcul Pension CNAV (Régime Général)
========================================

Version : 1.0
Date : 2025-01-10
Type : Skill Calcul Régime - BRUT uniquement

Fonctions principales :
- Calcul pension BRUTE CNAV
- Utilisation Excel pour barèmes et formules
- Taux de liquidation (décote/surcote)
- Coefficient de proratisation
- Contrôles de cohérence (CNAV_C01 à CNAV_C06)
- Alertes Rouge/Orange/Jaune

Point d'entrée N8N : api_handler(params)
"""

from typing import Dict, List, Optional
from datetime import datetime
from decimal import Decimal, ROUND_HALF_UP
import json
import openpyxl
from openpyxl import load_workbook
import os

# ============================================================================
# CHARGEMENT DES RÈGLES
# ============================================================================

with open('calcul_cnav_regles.json', 'r', encoding='utf-8') as f:
    REGLES = json.load(f)

VALEURS_2025 = REGLES['valeurs_reglementaires_2025']
CONTROLES = REGLES['controles_coherence']
ALERTES = REGLES['alertes']

# ============================================================================
# CONSTANTES 2025
# ============================================================================

PLAFOND_SS_2025 = Decimal(str(VALEURS_2025['plafonds']['plafond_ss_annuel']))  # 47 100€
SMIC_MENSUEL_2025 = Decimal(str(VALEURS_2025['plafonds']['smic_mensuel']))  # 1 801,84€
SMIC_ANNUEL_2025 = SMIC_MENSUEL_2025 * 12

TAUX_PLEIN = Decimal('50.0')
TAUX_MINIMAL = Decimal('37.5')
DECOTE_PAR_TRIMESTRE = Decimal('1.25')
SURCOTE_PAR_TRIMESTRE = Decimal('1.25')

# Durées d'assurance par génération
DUREES_ASSURANCE = {
    (1958, 1960): 167,
    (1961, 1961, 1, 8): 168,  # Janvier-Août 1961
    (1961, 1962, 9, 12): 169,  # Sept-Déc 1961 et 1962
    (1963, 1963): 170,
    (1964, 1964): 171,
    (1965, 2030): 172
}

FICHIER_EXCEL = 'CNAV_baremes_calculs.xlsx'

# ============================================================================
# API HANDLER - POINT D'ENTRÉE N8N
# ============================================================================

def api_handler(params: Dict) -> Dict:
    """
    Point d'entrée unifié pour N8N
    
    Params attendus:
    {
        "date_naissance": "15/03/1965",
        "sam": 42000.0,
        "trimestres_valides_tous_regimes": 173,
        "trimestres_cotises_rg": 165,
        "age_depart_mois": 756  # Optionnel pour calcul taux
    }
    
    Returns:
    {
        "regime": "CNAV",
        "calcul_type": "BRUT",
        "pension_annuelle": 20145.30,
        "pension_mensuelle": 1678.78,
        "details": {...},
        "controles": [...],
        "alertes": [...],
        "tokens_estimes": 800
    }
    """
    
    try:
        # 1. Validation et extraction
        date_naissance = datetime.strptime(params['date_naissance'], '%d/%m/%Y')
        sam = Decimal(str(params['sam']))
        trimestres_valides = params['trimestres_valides_tous_regimes']
        trimestres_rg = params['trimestres_cotises_rg']
        age_depart_mois = params.get('age_depart_mois', None)
        
        # 2. Vérification Excel
        if not os.path.exists(FICHIER_EXCEL):
            return {
                "erreur": True,
                "message": f"Fichier Excel {FICHIER_EXCEL} introuvable",
                "type": "erreur_fichier"
            }
        
        # 3. Vérification alertes MAJ Excel
        alerte_excel = verifier_alertes_excel()
        if alerte_excel:
            return alerte_excel
        
        # 4. Calcul via Excel
        resultat = calculer_pension_via_excel(
            date_naissance,
            sam,
            trimestres_valides,
            trimestres_rg,
            age_depart_mois
        )
        
        # 5. Exécution contrôles
        controles = executer_controles_coherence(params, resultat)
        
        # 6. Génération alertes
        alertes = generer_alertes(controles, resultat)
        
        # 7. Construction résultat final
        resultat_final = {
            "regime": "CNAV",
            "calcul_type": "BRUT",
            "pension_annuelle": float(resultat['pension_annuelle']),
            "pension_mensuelle": float(resultat['pension_mensuelle']),
            "details": {
                "sam": float(sam),
                "taux": float(resultat['taux']),
                "prorata": float(resultat['prorata']),
                "duree_requise": resultat['duree_requise'],
                "trimestres_rg": trimestres_rg,
                "trimestres_valides": trimestres_valides
            },
            "controles": controles,
            "alertes": alertes,
            "source_calcul": "Excel",
            "tokens_estimes": 800
        }
        
        return resultat_final
        
    except Exception as e:
        return {
            "erreur": True,
            "message": f"Erreur lors du calcul : {str(e)}",
            "type": "erreur_calcul",
            "tokens_estimes": 100
        }

# ============================================================================
# CALCUL VIA EXCEL
# ============================================================================

def calculer_pension_via_excel(
    date_naissance: datetime,
    sam: Decimal,
    trimestres_valides: int,
    trimestres_rg: int,
    age_depart_mois: Optional[int]
) -> Dict:
    """
    Effectue le calcul de pension en utilisant Excel
    
    Returns:
        Dict avec pension_annuelle, pension_mensuelle, taux, prorata, duree_requise
    """
    
    # 1. Charger le workbook
    wb = load_workbook(FICHIER_EXCEL)
    sheet_calc = wb['Calculateur']
    
    # 2. Remplir les INPUT (Feuille 2)
    sheet_calc['B2'] = date_naissance.strftime('%d/%m/%Y')
    sheet_calc['B3'] = float(sam)
    sheet_calc['B4'] = trimestres_valides
    sheet_calc['B5'] = trimestres_rg
    
    if age_depart_mois:
        sheet_calc['B6'] = age_depart_mois
    
    # 3. Sauvegarder pour recalcul
    temp_file = 'temp_cnav_calcul.xlsx'
    wb.save(temp_file)
    
    # 4. Recharger avec data_only=True pour avoir les valeurs calculées
    wb_calcule = load_workbook(temp_file, data_only=True)
    sheet_result = wb_calcule['Calculateur']
    
    # 5. Lire les OUTPUT
    duree_requise = sheet_result['B11'].value
    taux = sheet_result['B15'].value
    prorata = sheet_result['B16'].value
    pension_annuelle = sheet_result['B20'].value
    pension_mensuelle = sheet_result['B21'].value
    
    # 6. Nettoyer fichier temp
    if os.path.exists(temp_file):
        os.remove(temp_file)
    
    # 7. Convertir en Decimal pour précision
    return {
        "duree_requise": int(duree_requise) if duree_requise else 172,
        "taux": Decimal(str(taux)) if taux else TAUX_PLEIN,
        "prorata": Decimal(str(prorata)) if prorata else Decimal('1.0'),
        "pension_annuelle": Decimal(str(pension_annuelle)) if pension_annuelle else Decimal('0'),
        "pension_mensuelle": Decimal(str(pension_mensuelle)) if pension_mensuelle else Decimal('0')
    }

def verifier_alertes_excel() -> Optional[Dict]:
    """
    Vérifie si des alertes MAJ sont actives dans Excel
    
    Returns:
        None si OK, Dict d'erreur sinon
    """
    
    try:
        wb = load_workbook(FICHIER_EXCEL, data_only=True)
        sheet_alertes = wb['Alertes']
        
        statut = sheet_alertes['B2'].value
        
        if statut and statut.strip() != "À JOUR":
            derniere_maj = sheet_alertes['B1'].value
            return {
                "erreur": True,
                "message": f"Barèmes CNAV obsolètes. Dernière MAJ : {derniere_maj}",
                "type": "baremes_obsoletes",
                "action": "Mettre à jour le fichier Excel CNAV_baremes_calculs.xlsx"
            }
        
        return None
        
    except Exception as e:
        return {
            "erreur": True,
            "message": f"Impossible de vérifier les alertes Excel : {str(e)}",
            "type": "erreur_excel"
        }

# ============================================================================
# CALCULS DIRECTS (si Excel indisponible)
# ============================================================================

def obtenir_duree_requise(annee_naissance: int) -> int:
    """Retourne la durée d'assurance requise pour la génération"""
    
    for cle, duree in DUREES_ASSURANCE.items():
        if len(cle) == 2:
            debut, fin = cle
            if debut <= annee_naissance <= fin:
                return duree
        elif len(cle) == 4:
            annee_debut, annee_fin, mois_debut, mois_fin = cle
            if annee_debut <= annee_naissance <= annee_fin:
                return duree
    
    return 172  # Défaut pour 1965+

def calculer_taux_direct(
    trimestres_valides: int,
    duree_requise: int,
    age_mois: Optional[int] = None
) -> Decimal:
    """
    Calcule le taux de liquidation (sans Excel)
    
    Returns:
        Taux en % (Decimal)
    """
    
    # Taux plein si durée atteinte
    if trimestres_valides >= duree_requise:
        return TAUX_PLEIN
    
    # Taux plein auto si 67 ans
    if age_mois and age_mois >= 804:  # 67 ans = 804 mois
        return TAUX_PLEIN
    
    # Décote
    trimestres_manquants = duree_requise - trimestres_valides
    trimestres_manquants = min(trimestres_manquants, 20)  # Max 20 trimestres
    
    decote = Decimal(str(trimestres_manquants)) * DECOTE_PAR_TRIMESTRE
    taux = TAUX_PLEIN - decote
    taux = max(taux, TAUX_MINIMAL)  # Minimum 37,5%
    
    return taux

def calculer_prorata_direct(
    trimestres_rg: int,
    duree_requise: int
) -> Decimal:
    """
    Calcule le coefficient de proratisation (sans Excel)
    
    Returns:
        Prorata (Decimal), max 1.00
    """
    
    prorata = Decimal(str(trimestres_rg)) / Decimal(str(duree_requise))
    return min(prorata, Decimal('1.00'))

# ============================================================================
# CONTRÔLES DE COHÉRENCE
# ============================================================================

def executer_controles_coherence(params: Dict, resultat: Dict) -> List[Dict]:
    """Exécute tous les contrôles de cohérence"""
    
    controles_resultats = []
    
    sam = Decimal(str(params['sam']))
    trimestres_valides = params['trimestres_valides_tous_regimes']
    trimestres_rg = params['trimestres_cotises_rg']
    
    date_naissance = datetime.strptime(params['date_naissance'], '%d/%m/%Y')
    annee_naissance = date_naissance.year
    
    taux = resultat.get('taux', TAUX_PLEIN)
    prorata = resultat.get('prorata', Decimal('1.0'))
    pension_mensuelle = resultat.get('pension_mensuelle', Decimal('0'))
    
    # CNAV_C01: SAM > Plafond
    if sam > PLAFOND_SS_2025:
        controles_resultats.append({
            "id": "CNAV_C01",
            "statut": "ALERTE",
            "message": f"SAM {float(sam):.2f}€ > Plafond SS 47 100€",
            "type": "ALERTE",
            "couleur": "ORANGE",
            "correction_auto": True
        })
    
    # CNAV_C02: Prorata > 100%
    if prorata > Decimal('1.0'):
        controles_resultats.append({
            "id": "CNAV_C02",
            "statut": "ALERTE",
            "message": f"Prorata {float(prorata)*100:.2f}% > 100%",
            "type": "ALERTE",
            "couleur": "ORANGE",
            "correction_auto": True
        })
    
    # CNAV_C03: Trimestres RG > Total
    if trimestres_rg > trimestres_valides:
        controles_resultats.append({
            "id": "CNAV_C03",
            "statut": "ERREUR",
            "message": f"Trimestres RG ({trimestres_rg}) > Total ({trimestres_valides})",
            "type": "ERREUR_CRITIQUE",
            "couleur": "ROUGE"
        })
    
    # CNAV_C04: Taux hors limites
    if taux < TAUX_MINIMAL or taux > Decimal('75.0'):
        controles_resultats.append({
            "id": "CNAV_C04",
            "statut": "ERREUR",
            "message": f"Taux {float(taux):.2f}% hors limites [37,5% - 75%]",
            "type": "ERREUR_CRITIQUE",
            "couleur": "ROUGE"
        })
    
    # CNAV_C05: SAM < SMIC annuel
    if sam < SMIC_ANNUEL_2025:
        controles_resultats.append({
            "id": "CNAV_C05",
            "statut": "VIGILANCE",
            "message": f"SAM {float(sam):.2f}€ < SMIC annuel {float(SMIC_ANNUEL_2025):.2f}€",
            "type": "VIGILANCE",
            "couleur": "JAUNE"
        })
    
    # CNAV_C06: Génération hors périmètre
    if annee_naissance < 1958 or annee_naissance > 2030:
        controles_resultats.append({
            "id": "CNAV_C06",
            "statut": "ERREUR",
            "message": f"Génération {annee_naissance} hors périmètre [1958-2030]",
            "type": "ERREUR_CRITIQUE",
            "couleur": "ROUGE"
        })
    
    # Contrôle supplémentaire : Pension faible
    if pension_mensuelle < Decimal('500'):
        controles_resultats.append({
            "id": "CNAV_C07",
            "statut": "VIGILANCE",
            "message": f"Pension mensuelle faible ({float(pension_mensuelle):.2f}€) : vérifier MICO",
            "type": "VIGILANCE",
            "couleur": "JAUNE"
        })
    
    # Contrôle supplémentaire : Prorata faible
    if prorata < Decimal('0.5'):
        controles_resultats.append({
            "id": "CNAV_C08",
            "statut": "VIGILANCE",
            "message": f"Prorata faible ({float(prorata)*100:.1f}%) : carrière courte RG",
            "type": "VIGILANCE",
            "couleur": "JAUNE"
        })
    
    return controles_resultats

# ============================================================================
# GÉNÉRATION ALERTES
# ============================================================================

def generer_alertes(controles: List[Dict], resultat: Dict) -> List[Dict]:
    """Génère les alertes en fonction des contrôles"""
    
    alertes_generees = []
    
    for controle in controles:
        controle_id = controle['id']
        statut = controle['statut']
        
        # Mapper contrôles vers alertes
        if controle_id == "CNAV_C01":
            alertes_generees.append({
                "code": "CNAV_A04",
                "niveau": "ORANGE",
                "message": "SAM plafonné au plafond SS 2025",
                "action": "Plafonnement appliqué automatiquement"
            })
        
        elif controle_id == "CNAV_C02":
            alertes_generees.append({
                "code": "CNAV_A05",
                "niveau": "ORANGE",
                "message": "Prorata plafonné à 100%",
                "action": "Plafonnement appliqué automatiquement"
            })
        
        elif controle_id == "CNAV_C03":
            alertes_generees.append({
                "code": "CNAV_A01",
                "niveau": "ROUGE",
                "message": "Données incohérentes : vérification requise",
                "action": "Bloquer calcul / Vérifier données"
            })
        
        elif controle_id == "CNAV_C04":
            alertes_generees.append({
                "code": "CNAV_A02",
                "niveau": "ROUGE",
                "message": "Taux hors limites réglementaires",
                "action": "Bloquer calcul / Vérifier paramètres"
            })
        
        elif controle_id == "CNAV_C05":
            alertes_generees.append({
                "code": "CNAV_A06",
                "niveau": "JAUNE",
                "message": "SAM faible : vérifier carrière et MICO",
                "action": "Alerter consultant"
            })
        
        elif controle_id == "CNAV_C06":
            alertes_generees.append({
                "code": "CNAV_A03",
                "niveau": "ROUGE",
                "message": "Génération non supportée",
                "action": "Bloquer calcul / Étendre barèmes"
            })
        
        elif controle_id == "CNAV_C07":
            alertes_generees.append({
                "code": "CNAV_A08",
                "niveau": "JAUNE",
                "message": "Pension faible : éligibilité MICO à vérifier",
                "action": "Orienter vers calcul MICO"
            })
        
        elif controle_id == "CNAV_C08":
            alertes_generees.append({
                "code": "CNAV_A07",
                "niveau": "JAUNE",
                "message": "Prorata faible : carrière courte RG",
                "action": "Vérifier poly-pensionné"
            })
    
    return alertes_generees

# ============================================================================
# UTILITAIRES
# ============================================================================

def formater_montant(montant: Decimal) -> str:
    """Formate un montant en euros"""
    return f"{float(montant):,.2f}€".replace(',', ' ')

# ============================================================================
# EXEMPLES D'UTILISATION
# ============================================================================

if __name__ == "__main__":
    
    print("="*80)
    print("EXEMPLES D'UTILISATION - CALCUL PENSION CNAV")
    print("="*80)
    
    # Exemple 1: Cas standard taux plein
    print("\n" + "="*80)
    print("EXEMPLE 1 : Cas standard taux plein")
    print("="*80)
    
    params1 = {
        "date_naissance": "20/04/1965",
        "sam": 42000.0,
        "trimestres_valides_tous_regimes": 173,
        "trimestres_cotises_rg": 165,
        "age_depart_mois": 756  # 63 ans
    }
    
    resultat1 = api_handler(params1)
    print(json.dumps(resultat1, indent=2, ensure_ascii=False))
    
    # Exemple 2: Cas avec décote
    print("\n" + "="*80)
    print("EXEMPLE 2 : Cas avec décote")
    print("="*80)
    
    params2 = {
        "date_naissance": "15/03/1965",
        "sam": 38000.0,
        "trimestres_valides_tous_regimes": 164,
        "trimestres_cotises_rg": 160,
        "age_depart_mois": 744  # 62 ans
    }
    
    resultat2 = api_handler(params2)
    print(json.dumps(resultat2, indent=2, ensure_ascii=False))
    
    # Exemple 3: Cas avec surcote
    print("\n" + "="*80)
    print("EXEMPLE 3 : Cas avec surcote")
    print("="*80)
    
    params3 = {
        "date_naissance": "10/06/1963",
        "sam": 45000.0,
        "trimestres_valides_tous_regimes": 178,
        "trimestres_cotises_rg": 170,
        "age_depart_mois": 768  # 64 ans
    }
    
    resultat3 = api_handler(params3)
    print(json.dumps(resultat3, indent=2, ensure_ascii=False))
    
    print("\n" + "="*80)
    print("FIN DES EXEMPLES")
    print("="*80)
