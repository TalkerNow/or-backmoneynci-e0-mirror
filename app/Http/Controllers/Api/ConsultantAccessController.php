<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ConsultantAccessController extends Controller
{
    private const AUTHORIZED_IDS = [4, 1271, 1638];

    private function checkAdminAccess(): ?JsonResponse
    {
        $user = auth()->user();
        if (!in_array((int) $user->id, self::AUTHORIZED_IDS)) {
            return response()->json(['error' => 'Accès non autorisé.'], 403);
        }
        return null;
    }

    /**
     * GET /api/v1/consultant-access
     */
    public function index(): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $consultants = DB::table('consultants_access')->orderBy('nom')->get();
        return response()->json($consultants);
    }

    /**
     * POST /api/v1/consultant-access
     */
    public function store(Request $request): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $request->validate([
            'nom'                  => 'required|string|max:100',
            'prenom'               => 'required|string|max:100',
            'date_de_naissance'    => 'required|date_format:Y-m-d',
            'access_type'          => 'required|in:unlimited_pass,credits',
            'pass_expiration_date' => 'nullable|date',
            'remaining_credits'    => 'required_if:access_type,credits|integer|min:0',
        ]);

        $id = DB::table('consultants_access')->insertGetId([
            'nom'                  => $request->input('nom'),
            'prenom'               => $request->input('prenom'),
            'date_de_naissance'    => $request->input('date_de_naissance'),
            'access_type'          => $request->input('access_type'),
            'pass_expiration_date' => $request->input('pass_expiration_date'),
            'remaining_credits'    => $request->input('remaining_credits', 0),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return response()->json(DB::table('consultants_access')->find($id), 201);
    }

    /**
     * PUT /api/v1/consultant-access/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $consultant = DB::table('consultants_access')->find($id);
        if (!$consultant) {
            return response()->json(['error' => 'Consultant introuvable.'], 404);
        }

        $request->validate([
            'nom'                  => 'sometimes|string|max:100',
            'prenom'               => 'sometimes|string|max:100',
            'date_de_naissance'    => 'sometimes|date_format:Y-m-d',
            'access_type'          => 'sometimes|in:unlimited_pass,credits',
            'pass_expiration_date' => 'nullable|date',
            'remaining_credits'    => 'sometimes|integer|min:0',
        ]);

        DB::table('consultants_access')->where('id', $id)->update(
            array_merge($request->only([
                'nom', 'prenom', 'date_de_naissance',
                'access_type', 'pass_expiration_date', 'remaining_credits',
            ]), ['updated_at' => now()])
        );

        return response()->json(DB::table('consultants_access')->find($id));
    }

    /**
     * DELETE /api/v1/consultant-access/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $deleted = DB::table('consultants_access')->where('id', $id)->delete();
        if (!$deleted) {
            return response()->json(['error' => 'Consultant introuvable.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/consultant-access/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'last_name'     => 'required|string|max:100',
            'first_name'    => 'required|string|max:100',
            'date_of_birth' => 'required|date_format:Y-m-d',
        ]);

        $webhookUrl = config('services.n8n.consultant_access_url');

        if (empty($webhookUrl)) {
            return response()->json(['error' => 'Webhook non configuré.'], 503);
        }

        $n8nResponse = Http::timeout(15)->post($webhookUrl, [
            'last_name'     => $request->input('last_name'),
            'first_name'    => $request->input('first_name'),
            'date_of_birth' => $request->input('date_of_birth'),
        ]);

        if ($n8nResponse->status() === 200) {
            return response()->json(['authorized' => true], 200);
        }

        if ($n8nResponse->status() === 403) {
            return response()->json([
                'error' => $n8nResponse->json('error') ?? 'Accès refusé.',
            ], 403);
        }

        return response()->json(['error' => 'Erreur lors de la vérification d\'accès.'], 502);
    }
}
