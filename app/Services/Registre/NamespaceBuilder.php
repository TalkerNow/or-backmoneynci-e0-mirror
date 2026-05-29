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
 * salaire_annuel_moyen, date_naissance_client, + PRIX_ACHAT_POINT_AA_2025.
 */
class NamespaceBuilder
{
    public const PRIX_ACHAT_POINT_AA_2025 = 20.1877;

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
            // Constante réglementaire (source AGIRC-ARRCO 2025)
            'PRIX_ACHAT_POINT_AA_2025' => self::PRIX_ACHAT_POINT_AA_2025,
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

        return $ns;
    }
}
