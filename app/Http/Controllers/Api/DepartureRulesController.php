<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartureRule;
use App\Models\DepartureRulesHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartureRulesController extends Controller
{
    private function requireAuth(): ?JsonResponse
    {
        if (! auth('api')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return null;
    }

    private function requireAdmin(): ?JsonResponse
    {
        if ($err = $this->requireAuth()) {
            return $err;
        }
        $auth = auth('api')->user();
        if (strtolower($auth->role ?? '') !== 'admin') {
            return response()->json(['error' => 'Forbidden — accès réservé admin'], 403);
        }
        return null;
    }

    public function index(): JsonResponse
    {
        if ($err = $this->requireAuth()) {
            return $err;
        }
        $rules = DepartureRule::ordered()->get()->map->toApiArray();
        return response()->json($rules);
    }

    public function update(Request $request): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $data = $request->validate([
            'rules'              => 'required|array|size:21',
            'rules.*.id'         => 'required|integer|exists:departure_rules,id',
            'rules.*.age_months' => 'required|integer|between:720,816',
            'rules.*.trim'       => 'required|integer|between:150,180',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rules'] as $row) {
                DepartureRule::where('id', $row['id'])->update([
                    'age_months' => $row['age_months'],
                    'trim'       => $row['trim'],
                    'updated_by' => auth('api')->id(),
                ]);
            }

            DepartureRulesHistory::create([
                'rules_json' => DepartureRule::ordered()->get()->toArray(),
                'saved_by'   => auth('api')->id(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function history(): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $history = DepartureRulesHistory::with('savedBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($h) => [
                'id'         => $h->id,
                'saved_by'   => $h->savedBy?->name,
                'created_at' => $h->created_at->toIso8601String(),
                'preview'    => collect($h->rules_json)->take(3)->map(fn ($r) => [
                    'trim'       => $r['trim'],
                    'age_months' => $r['age_months'],
                ]),
            ]);

        return response()->json($history);
    }

    public function restore(int $historyId): JsonResponse
    {
        if ($err = $this->requireAdmin()) {
            return $err;
        }

        $snapshot = DepartureRulesHistory::findOrFail($historyId);

        DB::transaction(function () use ($snapshot) {
            foreach ($snapshot->rules_json as $row) {
                DepartureRule::where('id', $row['id'])->update([
                    'age_months' => $row['age_months'],
                    'trim'       => $row['trim'],
                    'updated_by' => auth('api')->id(),
                ]);
            }

            DepartureRulesHistory::create([
                'rules_json' => $snapshot->rules_json,
                'saved_by'   => auth('api')->id(),
            ]);
        });

        return response()->json(['success' => true]);
    }
}
