<?php

namespace App\Repositories;

use App\Models\AuditLog;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditLogRepository
{
    /**
     * Crée une entrée d'audit. Les entrées sont immuables après création.
     */
    public function create(array $data): AuditLog
    {
        return AuditLog::create($data);
    }

    /**
     * Récupère l'historique d'un client, du plus récent au plus ancien.
     */
    public function getByClientId(int $clientId, int $perPage = 20): LengthAwarePaginator
    {
        return AuditLog::where('client_id', $clientId)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Récupère l'historique déclenché par un consultant.
     */
    public function getByUserId(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return AuditLog::where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Récupère une entrée spécifique — pour rejouer ou auditer un calcul.
     */
    public function getById(int $id): ?AuditLog
    {
        return AuditLog::find($id);
    }
}
