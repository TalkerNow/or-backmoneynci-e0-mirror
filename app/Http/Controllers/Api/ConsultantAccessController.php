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
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié.'], 401);
        }
        if (!in_array((int) $user->id, self::AUTHORIZED_IDS)) {
            return response()->json(['error' => 'Accès non autorisé.'], 403);
        }
        return null;
    }

    /**
     * GET /api/v1/consultant-access
     * Retourne tous les users role=Consultant avec leur accès (ou null si aucun).
     */
    public function index(): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $consultants = DB::table('users')
            ->leftJoin('consultants_access', 'users.id', '=', 'consultants_access.user_id')
            ->where('users.role', 'Consultant')
            ->select([
                'users.id as user_id',
                'users.name',
                'users.email',
                'consultants_access.id as access_id',
                'consultants_access.access_type',
                'consultants_access.remaining_credits',
                'consultants_access.pass_expiration_date',
            ])
            ->orderBy('users.name')
            ->get();

        return response()->json($consultants);
    }

    /**
     * POST /api/v1/consultant-access
     * Crée un accès pour un consultant (user_id requis).
     * Auto-remplit nom/prenom/date_de_naissance depuis personal_informations.
     */
    public function store(Request $request): JsonResponse
    {
        if ($err = $this->checkAdminAccess()) return $err;

        $request->validate([
            'user_id'              => 'required|integer|exists:users,id|unique:consultants_access,user_id',
            'access_type'          => 'required|in:unlimited_pass,credits',
            'pass_expiration_date' => 'nullable|date',
            'remaining_credits'    => 'required_if:access_type,credits|integer|min:0',
        ]);

        $user = DB::table('users')->where('id', $request->input('user_id'))->first();

        $id = DB::table('consultants_access')->insertGetId([
            'user_id'              => $request->input('user_id'),
            'email'                => $user->email ?? null,
            'name'                 => $user->name ?? null,
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

        $access = DB::table('consultants_access')->find($id);
        if (!$access) {
            return response()->json(['error' => 'Accès introuvable.'], 404);
        }

        $request->validate([
            'access_type'          => 'sometimes|in:unlimited_pass,credits',
            'pass_expiration_date' => 'nullable|date',
            'remaining_credits'    => 'sometimes|integer|min:0',
        ]);

        DB::table('consultants_access')->where('id', $id)->update(
            array_merge($request->only(['access_type', 'pass_expiration_date', 'remaining_credits']),
            ['updated_at' => now()])
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
            return response()->json(['error' => 'Accès introuvable.'], 404);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/consultant-access/verify
     * Vérifie l'accès du consultant authentifié et décrémente 1 crédit via n8n.
     */
    public function verify(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié.'], 401);
        }

        if ($user->role !== 'Consultant') {
            return response()->json(['authorized' => true], 200);
        }

        $webhookUrl = config('services.n8n.consultant_access_url');

        if (empty($webhookUrl)) {
            return response()->json(['error' => 'Webhook non configuré.'], 503);
        }

        $n8nResponse = Http::timeout(15)->post($webhookUrl, [
            'user_id' => (int) $user->id,
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
