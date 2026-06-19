# 📦 NOTE DE LIVRAISON - MODULE CHÔMAGE

## 📋 INFORMATIONS GÉNÉRALES
**Module** : Chômage  
**Version** : 1.0  
**Date de livraison** : 07 avril 2026  
**Statut** : ✅ COMPLET - Prêt pour intégration N8N

## 🎯 OBJECTIF DU MODULE
Automatiser l'analyse des périodes de chômage et leur impact sur la retraite française.

## 📦 FICHIERS LIVRÉS (4)
1. `calcul_chomage_retraite.py`
2. `chomage_retraite_regles.json`
3. `SKILL_chomage.md`
4. `LIVRAISON_chomage.md`

## 🚀 INSTRUCTIONS D'INTÉGRATION N8N
```json
{
  "script": "from calcul_chomage_retraite import api_handler; return api_handler($json)",
  "inputDataFieldName": "json"
}
```

## ✅ TESTS DE VALIDATION SUGGÉRÉS
- Chômage indemnisé simple.
- Non indemnisé après fin de droits.
- Non indemnisé senior.
- Première période non indemnisée autonome.
- Dates invalides.

## ⚠️ POINTS D'ATTENTION
- Ne pas confondre trimestres assimilés et cotisés.
- Isoler la logique carrière longue.
- Contrôler le relevé Agirc-Arrco sur chômage indemnisé.
