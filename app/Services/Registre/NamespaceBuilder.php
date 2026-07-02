<?php

namespace App\Services\Registre;

/**
 * Construit le namespace de variables pour RuleEvaluator à partir du payload
 * d'entrée que Laravel envoie déjà à n8n (SimulationRetraiteController::buildN8nPayload).
 *
 * MVP : uniquement les variables dérivables des ENTRÉES (n8n ne renvoie pas
 * calcul_json aujourd'hui). Les règles référençant des variables absentes sont
 * ignorées proprement par RuleEvaluator (liste "skipped").
 *
 * Variables produites : sexe, nombre_enfants, trimestres_total,
 * salaire_annuel_moyen, date_naissance_client, salaire_brut_max,
 * + PRIX_ACHAT_POINT_AA_2025, PASS_2025.
 */
class NamespaceBuilder
{
    public const PRIX_ACHAT_POINT_AA_2025 = 20.1877;

    /** Plafond annuel de la Sécurité sociale 2025 (€/an). Sert au seuil tranche C (4 × PASS). */
    public const PASS_2025 = 47100;

    /**
     * Variante Consultation Retraite : la donnée vient de frozen_data.meta
     * (sexe, enfants, date_naissance) + frozen_data.totaux (trimestres_total, sam).
     * Réutilise le mapping de fromPayload (mêmes clés).
     */
    public static function fromFrozenData(?array $meta, ?array $totaux): array
    {
        return self::fromPayload([
            'client' => $meta ?? [],
            'totaux' => $totaux ?? [],
        ]);
    }

    /**
     * Variables dérivées de calcul_json (réponse du moteur de simulation,
     * renvoyée par n8n une fois le Respond node patché — source py-port :8002).
     * Chaque variable n'est produite que si sa source existe : une variable
     * absente => la règle qui la référence est proprement "skipped".
     *
     * Débloque : R001 (trimestres_enfants), R002 (age_legal),
     * R006 (nb_trim_decote / manquants_duree / age_depart_ans).
     * R005 / R007 / R008 / R009 restent skippées : le moteur n'expose pas
     * (encore) pass_utilise par année, trim décote AGIRC, top-25 SAM,
     * ni le prix d'achat du point utilisé.
     */
    public static function fromCalcul(?array $calcul): array
    {
        if (empty($calcul)) {
            return [];
        }

        $ns = [];

        // R001 — trimestres pour enfants attribués par le moteur (MDA).
        $enfants = is_array($calcul['enfants'] ?? null) ? $calcul['enfants'] : [];
        if (isset($enfants['trim_bonus_total']) && is_numeric($enfants['trim_bonus_total'])) {
            $ns['trimestres_enfants'] = (int) $enfants['trim_bonus_total'];
        }

        // Scénario H1 = départ à l'âge légal (ancre des règles R002 / R006).
        $scenarios = is_array($calcul['scenarios'] ?? null) ? $calcul['scenarios'] : [];
        $h1 = is_array($scenarios['H1'] ?? null) ? $scenarios['H1'] : [];

        if (isset($h1['age_depart_annees']) && is_numeric($h1['age_depart_annees'])) {
            $annees = (float) $h1['age_depart_annees'];
            $mois   = is_numeric($h1['age_depart_mois'] ?? null) ? (float) $h1['age_depart_mois'] : 0.0;
            // R002 : âge légal en années décimales (61a9m => 61.75 < 62 => alerte).
            $ns['age_legal']      = $annees + $mois / 12;
            $ns['age_depart_ans'] = (int) $annees;
        }

        // R006 : décote en trimestres. Le moteur expose decote_pct = trim × 1,25 %
        // (DECOTE_PAR_TRIM = 0.0125) — la division est exacte à 2 décimales.
        if (isset($h1['decote_pct']) && is_numeric($h1['decote_pct'])) {
            $ns['nb_trim_decote'] = (int) round(((float) $h1['decote_pct']) / 1.25);
        }
        if (isset($h1['duree_requise'], $h1['trimestres_acquis_tous'])
            && is_numeric($h1['duree_requise']) && is_numeric($h1['trimestres_acquis_tous'])) {
            $ns['manquants_duree'] = max(0, (int) $h1['duree_requise'] - (int) $h1['trimestres_acquis_tous']);
        }

        return $ns;
    }

    public static function fromPayload(array $payload): array
    {
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $totaux = is_array($payload['totaux'] ?? null) ? $payload['totaux'] : [];

        $ns = [
            // Constantes réglementaires (sources AGIRC-ARRCO / Sécurité sociale 2025)
            'PRIX_ACHAT_POINT_AA_2025' => self::PRIX_ACHAT_POINT_AA_2025,
            'PASS_2025'                => self::PASS_2025,
        ];

        if (array_key_exists('sexe', $client) && $client['sexe'] !== null) {
            $ns['sexe'] = $client['sexe'];
        }
        if (array_key_exists('enfants', $client) && $client['enfants'] !== null) {
            $ns['nombre_enfants'] = $client['enfants'];
        }
        if (array_key_exists('date_naissance', $client) && $client['date_naissance'] !== null) {
            $ns['date_naissance_client'] = $client['date_naissance'];
        }
        if (array_key_exists('trimestres_total', $totaux) && $totaux['trimestres_total'] !== null) {
            $ns['trimestres_total'] = $totaux['trimestres_total'];
        }
        if (array_key_exists('sam', $totaux) && $totaux['sam'] !== null) {
            $ns['salaire_annuel_moyen'] = $totaux['sam'];
        }

        // Salaire brut annuel max de la carrière (revenu_brut NON plafonné) — sert à
        // détecter les hauts revenus en tranche C (salaire > 4 PASS). Le SAM ne convient
        // pas : il est plafonné à 1 PASS. Consommé par la règle R010 (Gate #2).
        $carriere = is_array($payload['carriere'] ?? null) ? $payload['carriere'] : [];
        $salaires = [];
        foreach ($carriere as $entry) {
            if (is_array($entry) && isset($entry['revenu_brut']) && is_numeric($entry['revenu_brut'])) {
                $salaires[] = (float) $entry['revenu_brut'];
            }
        }
        if (!empty($salaires)) {
            $max = max($salaires);
            $ns['salaire_brut_max'] = (fmod($max, 1.0) === 0.0) ? (int) $max : $max;
        }

        return $ns;
    }
}
