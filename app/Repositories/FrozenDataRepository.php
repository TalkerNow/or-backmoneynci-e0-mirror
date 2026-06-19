<?php

namespace App\Repositories;

use App\Exceptions\FrozenDataLockedException;
use App\Models\FrozenData;
use Carbon\Carbon;

class FrozenDataRepository
{
    /**
     * Récupère les données gelées d'un client.
     * Retourne null si aucune donnée n'existe encore.
     */
    public function getByUserId(int $userId): ?FrozenData
    {
        return FrozenData::where('user_id', $userId)->latest()->first();
    }

    /**
     * Crée ou met à jour les données carrière d'un client.
     * INTERDIT si les données sont gelées (locked_at non null).
     *
     * @throws FrozenDataLockedException
     */
    public function createOrUpdate(int $userId, array $data): FrozenData
    {
        $existing = $this->getByUserId($userId);

        if ($existing && $existing->isLocked()) {
            throw new FrozenDataLockedException($userId);
        }

        $payload = [
            'user_id'  => $userId,
            'source'   => $data['source'] ?? null,
            'meta'     => $data['meta'] ?? null,
            'carriere' => $data['carriere'] ?? null,
            'cipav'          => $data['cipav'] ?? null,
            'carpimko'       => $data['carpimko'] ?? $existing?->carpimko,
            'regimes_points' => $data['regimes_points'] ?? $existing?->regimes_points,
            'alertes'        => $data['alertes'] ?? null,
            'totaux'   => $data['totaux'] ?? null,
            // Choix consultant : repli sur l'existant pour ne JAMAIS les écraser au verrouillage
            'dates_retenues'    => $data['dates_retenues'] ?? $existing?->dates_retenues,
            'scenarios_choisis' => $data['scenarios_choisis'] ?? $existing?->scenarios_choisis,
        ];

        if ($existing) {
            $existing->fill($payload);
            $existing->save();
            return $existing;
        }

        return FrozenData::create($payload);
    }

    /**
     * Gèle les données d'un client après validation humaine.
     * Une fois gelées, elles ne peuvent plus être modifiées via createOrUpdate.
     * Pour modifier : appeler unlock() puis createOrUpdate().
     */
    public function lock(int $userId, ?int $lockedByUserId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        $frozen->locked_at = Carbon::now();
        $frozen->locked_by = $lockedByUserId;
        $frozen->save();

        return $frozen;
    }

    /**
     * Soft-delete les données carrière d'un client (conserve l'historique via deleted_at).
     * INTERDIT si les données sont gelées — il faut unlock d'abord.
     *
     * @throws FrozenDataLockedException
     */
    public function softDeleteByUserId(int $userId, ?int $deletedByUserId): ?FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            return null;
        }

        if ($frozen->isLocked()) {
            throw new FrozenDataLockedException($userId);
        }

        $frozen->deleted_by = $deletedByUserId;
        $frozen->save();
        $frozen->delete();

        return $frozen;
    }

    /**
     * Déverrouille les données d'un client pour permettre une correction.
     * Attention : toute modification après unlock force une re-validation consultant.
     */
    public function unlock(int $userId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        $frozen->locked_at = null;
        $frozen->locked_by = null;
        $frozen->save();

        return $frozen;
    }

    /**
     * Met à jour la date de départ retenue pour un client.
     * Autorisé même si les données sont gelées.
     *
     * @param int $userId
     * @param array|null $date  null pour effacer le choix
     * @param int|null $chosenByUserId
     */
    public function setDateRetenue(int $userId, ?array $date, ?int $chosenByUserId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        if ($date !== null) {
            $date['chosen_at'] = $date['chosen_at'] ?? Carbon::now()->toIso8601String();
            if ($chosenByUserId !== null) {
                $date['chosen_by'] = $chosenByUserId;
            }
        }

        $frozen->date_retenue = $date;
        $frozen->save();

        return $frozen;
    }

    /**
     * Met à jour le scénario retenu pour un client.
     * Autorisé même si les données sont gelées : le choix de scénario
     * est une décision post-validation, distincte de la carrière elle-même.
     *
     * @param int $userId
     * @param array|null $scenario  null pour effacer le choix
     * @param int|null $chosenByUserId
     */
    public function setScenarioChoisi(int $userId, ?array $scenario, ?int $chosenByUserId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        if ($scenario !== null) {
            $scenario['chosen_at'] = $scenario['chosen_at'] ?? Carbon::now()->toIso8601String();
            if ($chosenByUserId !== null) {
                $scenario['chosen_by'] = $chosenByUserId;
            }
        }

        $frozen->scenario_choisi = $scenario;
        $frozen->save();

        return $frozen;
    }

    /**
     * Remplace l'intégralité du tableau de scénarios retenus.
     * Chaque item a une identité forte via `dispositif_id` (un dispositif
     * n'apparaît qu'une fois). Les champs `chosen_at` / `chosen_by` sont
     * normalisés ici si absents.
     *
     * @param int $userId
     * @param array $scenarios  Tableau des scénarios (vide pour tout effacer)
     * @param int|null $chosenByUserId
     */
    public function setScenariosChoisis(int $userId, array $scenarios, ?int $chosenByUserId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        $now = Carbon::now()->toIso8601String();
        $normalized = [];
        $seen = [];
        foreach ($scenarios as $scenario) {
            if (!is_array($scenario) || empty($scenario['dispositif_id'])) {
                continue;
            }
            $dispId = $scenario['dispositif_id'];
            if (isset($seen[$dispId])) {
                // Dédup : on garde la dernière occurrence (le payload est plus récent)
                continue;
            }
            $seen[$dispId] = true;
            $scenario['chosen_at'] = $scenario['chosen_at'] ?? $now;
            if ($chosenByUserId !== null && empty($scenario['chosen_by'])) {
                $scenario['chosen_by'] = $chosenByUserId;
            }
            $normalized[] = $scenario;
        }

        $frozen->scenarios_choisis = $normalized;
        // Mirror le 1er élément dans la colonne singulière pour back-compat
        $frozen->scenario_choisi = $normalized[0] ?? null;
        $frozen->save();

        return $frozen;
    }

    /**
     * Remplace l'intégralité du tableau de dates retenues.
     * Identité d'un item : couple (type, date). Plusieurs `date_libre`
     * différentes sont autorisées.
     *
     * @param int $userId
     * @param array $dates  Tableau des dates (vide pour tout effacer)
     * @param int|null $chosenByUserId
     */
    public function setDatesRetenues(int $userId, array $dates, ?int $chosenByUserId): FrozenData
    {
        $frozen = $this->getByUserId($userId);

        if (!$frozen) {
            abort(404, "Aucune donnée carrière trouvée pour le client {$userId}.");
        }

        $now = Carbon::now()->toIso8601String();
        $normalized = [];
        $seen = [];
        foreach ($dates as $date) {
            if (!is_array($date) || empty($date['type'])) {
                continue;
            }
            $key = $date['type'] . '|' . ($date['date'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $date['chosen_at'] = $date['chosen_at'] ?? $now;
            if ($chosenByUserId !== null && empty($date['chosen_by'])) {
                $date['chosen_by'] = $chosenByUserId;
            }
            $normalized[] = $date;
        }

        $frozen->dates_retenues = $normalized;
        $frozen->date_retenue = $normalized[0] ?? null;
        $frozen->save();

        return $frozen;
    }
}
