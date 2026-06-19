"""
TDD — build_namespace : calcul_json (sortie api_handler simulation_retraite)
+ params d'entrée + frozen passthrough → namespace de variables documentées
consommé par evaluate_rules.

Contrat (variables produites quand dérivables) :
- trimestres_total          <- calc_input.trimestres_valides_tous_regimes
- salaire_annuel_moyen      <- calc_input.sam
- date_naissance_client     <- calc_input.date_naissance (objet date)
- age_depart_ans            <- scenario[code].age_depart_annees
- manquants_duree           <- max(0, duree_requise - trimestres_tous_at_depart)
- PRIX_ACHAT_POINT_AA_2025  <- constante injectée
- (+ tout champ de `frozen` fusionné tel quel : sexe, trimestres_enfants, ...)
Variables non dérivables → absentes (les règles concernées sont "skipped").
"""

import unittest
from datetime import date

from validation_autocontrole import build_namespace, PRIX_ACHAT_POINT_AA_2025


CALC_INPUT = {
    "date_naissance": "1962-05-15",
    "sam": 32000,
    "trimestres_cotises_rg": 160,
    "trimestres_valides_tous_regimes": 168,
}

CALC_RESULT = {
    "duree_requise": 168,
    "date_taux_plein": "2024-05-15",
    "scenarios": {
        "H1": {
            "age_depart_annees": 62,
            "trimestres_tous_at_depart": 160,
            "duree_requise": 168,
            "decote_pct": 0.0,
            "pension_totale_nette_mensuelle": 1500,
            "coeff_agirc": 0.9,
            "coeff_agirc_duree_mois": 36,
        },
    },
}


class TestBuildNamespace(unittest.TestCase):

    def test_maps_input_params(self):
        ns = build_namespace(CALC_INPUT, CALC_RESULT)
        self.assertEqual(ns["trimestres_total"], 168)
        self.assertEqual(ns["salaire_annuel_moyen"], 32000)
        self.assertEqual(ns["date_naissance_client"], date(1962, 5, 15))

    def test_derives_from_scenario(self):
        ns = build_namespace(CALC_INPUT, CALC_RESULT, scenario_code="H1")
        self.assertEqual(ns["age_depart_ans"], 62)
        self.assertEqual(ns["manquants_duree"], 8)  # 168 - 160

    def test_injects_constants(self):
        ns = build_namespace(CALC_INPUT, CALC_RESULT)
        self.assertEqual(ns["PRIX_ACHAT_POINT_AA_2025"], PRIX_ACHAT_POINT_AA_2025)

    def test_merges_frozen_passthrough(self):
        ns = build_namespace(CALC_INPUT, CALC_RESULT,
                             frozen={"sexe": "H", "trimestres_enfants": 8})
        self.assertEqual(ns["sexe"], "H")
        self.assertEqual(ns["trimestres_enfants"], 8)

    def test_missing_scenario_is_omitted_not_crash(self):
        ns = build_namespace(CALC_INPUT, {"duree_requise": 168, "scenarios": {}})
        self.assertNotIn("age_depart_ans", ns)
        self.assertEqual(ns["trimestres_total"], 168)  # input toujours mappé

    def test_namespace_feeds_evaluate_rules_R003(self):
        # Intégration : namespace -> evaluate_rules pour R003 (>200 trimestres)
        from validation_autocontrole import evaluate_rules
        bad_input = dict(CALC_INPUT, trimestres_valides_tous_regimes=210)
        ns = build_namespace(bad_input, CALC_RESULT)
        rule = {"code": "R003", "condition": "trimestres_total > 200",
                "message": "Plus de 200 trimestres", "niveau": "CRITIQUE"}
        res = evaluate_rules([rule], ns)
        self.assertIsNotNone(res["arret_critique"])
        self.assertIn("R003", res["arret_critique"]["codes"])


if __name__ == "__main__":
    unittest.main()
