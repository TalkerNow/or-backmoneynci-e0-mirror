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
            'cipav'    => $data['cipav'] ?? null,
            'alertes'  => $data['alertes'] ?? null,
            'totaux'   => $data['totaux'] ?? null,
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
}
