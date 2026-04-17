<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FrozenDataLockedException;
use App\Http\Controllers\Controller;
use App\Repositories\FrozenDataRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrozenDataController extends Controller
{
    public function __construct(private FrozenDataRepository $repository)
    {
    }

    /**
     * GET /api/frozen_data/{user_id}
     * Retourne les données gelées d'un client (lecture seule).
     */
    public function show(int $userId): JsonResponse
    {
        $frozen = $this->repository->getByUserId($userId);

        if (!$frozen) {
            return response()->json(['message' => 'Aucune donnée carrière trouvée.'], 404);
        }

        // Append pre-computed Python-ready params at root level so the n8n CNAV workflow
        // can read them regardless of which field path it uses.
        $data = $frozen->toArray();

        // date_naissance : meta.date_naissance (YYYY-MM-DD) → DD/MM/YYYY
        $rawDate = $data['meta']['date_naissance'] ?? null;
        if ($rawDate) {
            $parts = explode('-', $rawDate);
            $data['date_naissance'] = count($parts) === 3
                ? "{$parts[2]}/{$parts[1]}/{$parts[0]}"
                : $rawDate;
        }

        // SAM : average of the best 25 revalorised salaries
        $carriere = $data['carriere'] ?? [];
        if (!empty($carriere)) {
            $revalos = array_column($carriere, 'salaire_revalo');
            rsort($revalos);
            $top25  = array_slice($revalos, 0, 25);
            $data['sam'] = count($top25) ? (int) round(array_sum($top25) / count($top25)) : 0;
        } else {
            $data['sam'] = 0;
        }

        // Trimestres fields expected by the Python script
        $totaux = $data['totaux'] ?? [];
        $data['trimestres_valides_tous_regimes'] = $totaux['trimestres_tous_regimes']
            ?? $totaux['trimestres_total']
            ?? 0;

        // trimestres_cotises_rg : UNIQUEMENT les trimestres CNAV (régime général),
        // pas tous régimes — pour que le calcul de proratisation CNAV soit exact
        $parRegime = $totaux['trimestres_par_regime'] ?? [];
        $data['trimestres_cotises_rg'] = $parRegime['cnav']
            ?? $totaux['trimestres_cnav']
            ?? $totaux['trimestres_regime_general']
            ?? $totaux['trimestres_cotises']  // fallback legacy (imprécis)
            ?? 0;

        // Also inject sam into totaux so the v1 Validation Gate (totaux.sam) passes
        if ($data['sam'] > 0) {
            $data['totaux']['sam'] = $data['sam'];
        }

        return response()->json($data);
    }

    /**
     * POST /api/frozen_data
     * Crée ou met à jour les données carrière (interdit si gelées).
     *
     * Body attendu :
     * {
     *   "user_id": 42,
     *   "source": "RIS_CNAV_2026",
     *   "meta": { "nom": "...", "prenom": "...", "date_naissance": "..." },
     *   "carriere": [...],
     *   "alertes": [...],
     *   "totaux": { "trimestres_cotises": 158, "trimestres_assimiles": 8, "trimestres_total": 166, "trimestres_requis": 172 }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'  => 'required|integer',
            'source'   => 'nullable|string|max:100',
            'meta'     => 'nullable|array',
            'carriere' => 'nullable|array',
            'cipav'    => 'nullable|array',
            'alertes'  => 'nullable|array',
            'totaux'   => 'nullable|array',
        ]);

        try {
            $frozen = $this->repository->createOrUpdate($data['user_id'], $data);
            return response()->json($frozen, $frozen->wasRecentlyCreated ? 201 : 200);
        } catch (FrozenDataLockedException $e) {
            return response()->json(['message' => $e->getMessage()], 423); // 423 Locked
        }
    }

    /**
     * POST /api/frozen_data/{user_id}/lock
     * Gèle les données après validation humaine du consultant.
     * Une fois gelées, elles deviennent la source de vérité pour tous les calculs.
     */
    public function lock(Request $request, int $userId): JsonResponse
    {
        $frozen = $this->repository->lock($userId, $request->user()?->id ?? 0);
        return response()->json($frozen);
    }

    /**
     * POST /api/frozen_data/{user_id}/unlock
     * Déverrouille les données pour permettre une correction.
     * Attention : toute simulation existante devient invalide après unlock + re-gel.
     */
    public function unlock(int $userId): JsonResponse
    {
        $frozen = $this->repository->unlock($userId);
        return response()->json($frozen);
    }
}
