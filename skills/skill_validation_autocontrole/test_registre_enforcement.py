"""
TDD — moteur d'enforcement piloté par le registre (Gate #2).

evaluate_rules(rules, namespace) reçoit les règles ACTIVES du registre
(chaque règle = {code, condition, message, niveau}) et un namespace de
variables issu du calcul, et renvoie :
  {
    "alertes": [{code, message, niveau}, ...],   # toutes les règles déclenchées
    "arret_critique": {raison, codes:[...]} | None,  # présent ssi >=1 CRITIQUE
    "skipped": [{code, reason}, ...]              # règles non évaluables / invalides
  }

La condition est une CHAÎNE évaluée par un évaluateur AST restreint
(jamais eval()) : comparaisons, and/or/not, +-*/%, min/max/abs/round/len,
accès variables et indexation uniquement.
"""

import unittest

from validation_autocontrole import evaluate_rules


R002 = {"code": "R002", "condition": "age_legal < 62",
        "message": "Age legal < 62 impossible depuis 2023", "niveau": "CRITIQUE"}
R001 = {"code": "R001", "condition": 'sexe == "H" and trimestres_enfants > 0',
        "message": "Trimestres enfants attribues a un homme", "niveau": "CRITIQUE"}
R003 = {"code": "R003", "condition": "trimestres_total > 200",
        "message": "Plus de 200 trimestres impossible", "niveau": "CRITIQUE"}
R_WARN = {"code": "R050", "condition": "salaire_annuel_moyen > 200000",
          "message": "SAM eleve, verifier plafonnement", "niveau": "AVERTISSEMENT"}


class TestEvaluateRules(unittest.TestCase):

    def test_critical_rule_fires_sets_arret_critique(self):
        res = evaluate_rules([R002], {"age_legal": 61})
        codes = [a["code"] for a in res["alertes"]]
        self.assertIn("R002", codes)
        self.assertIsNotNone(res["arret_critique"])
        self.assertIn("R002", res["arret_critique"]["codes"])

    def test_coherent_value_does_not_fire(self):
        res = evaluate_rules([R002], {"age_legal": 64})
        self.assertEqual(res["alertes"], [])
        self.assertIsNone(res["arret_critique"])

    def test_boolean_and_condition_man_fires_woman_does_not(self):
        fire = evaluate_rules([R001], {"sexe": "H", "trimestres_enfants": 8})
        self.assertIsNotNone(fire["arret_critique"])
        nofire = evaluate_rules([R001], {"sexe": "F", "trimestres_enfants": 8})
        self.assertIsNone(nofire["arret_critique"])

    def test_warning_never_blocks(self):
        res = evaluate_rules([R_WARN], {"salaire_annuel_moyen": 250000})
        codes = [a["code"] for a in res["alertes"]]
        self.assertIn("R050", codes)
        self.assertIsNone(res["arret_critique"])

    def test_multiple_rules_aggregate(self):
        res = evaluate_rules([R002, R003, R_WARN],
                             {"age_legal": 61, "trimestres_total": 210,
                              "salaire_annuel_moyen": 250000})
        codes = sorted(a["code"] for a in res["alertes"])
        self.assertEqual(codes, ["R002", "R003", "R050"])
        self.assertIsNotNone(res["arret_critique"])
        self.assertEqual(sorted(res["arret_critique"]["codes"]), ["R002", "R003"])

    def test_missing_variable_is_skipped_not_crash(self):
        res = evaluate_rules([R002], {})  # pas de age_legal
        self.assertEqual(res["alertes"], [])
        self.assertIsNone(res["arret_critique"])
        self.assertTrue(any(s["code"] == "R002" for s in res["skipped"]))

    def test_malicious_condition_is_rejected_not_executed(self):
        evil = {"code": "RX",
                "condition": "__import__('os').system('echo pwned')",
                "message": "x", "niveau": "CRITIQUE"}
        res = evaluate_rules([evil], {})
        self.assertIsNone(res["arret_critique"])
        self.assertTrue(any(s["code"] == "RX" for s in res["skipped"]))

    def test_attribute_access_rejected(self):
        evil = {"code": "RY", "condition": "age_legal.__class__",
                "message": "x", "niveau": "CRITIQUE"}
        res = evaluate_rules([evil], {"age_legal": 61})
        self.assertTrue(any(s["code"] == "RY" for s in res["skipped"]))

    def test_min_function_allowed_for_bouclier_67(self):
        rule = {"code": "R006",
                "condition": "nb_trim_decote != min(manquants_duree, (67 - age_depart_ans) * 4)",
                "message": "Bouclier 67 ans", "niveau": "CRITIQUE"}
        # age_depart=62 -> (67-62)*4=20 ; manquants=39 -> min=20 ; nb=39 != 20 -> fire
        fire = evaluate_rules([rule], {"nb_trim_decote": 39, "manquants_duree": 39,
                                       "age_depart_ans": 62})
        self.assertIsNotNone(fire["arret_critique"])
        # nb=20 == min -> no fire
        nofire = evaluate_rules([rule], {"nb_trim_decote": 20, "manquants_duree": 39,
                                         "age_depart_ans": 62})
        self.assertIsNone(nofire["arret_critique"])

    def test_empty_rules_returns_empty(self):
        res = evaluate_rules([], {"age_legal": 61})
        self.assertEqual(res["alertes"], [])
        self.assertIsNone(res["arret_critique"])


if __name__ == "__main__":
    unittest.main()
