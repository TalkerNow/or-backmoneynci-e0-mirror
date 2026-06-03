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
