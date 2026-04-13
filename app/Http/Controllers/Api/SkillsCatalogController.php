<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\SkillsCatalog;
use App\Models\SkillsCatalogHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SkillsCatalogController extends Controller
{
    /**
     * GET /api/v1/skills
     *
     * Liste les signatures des skills actifs (Level 1 Progressive Disclosure).
     * Retourne les métadonnées légères uniquement — pas de skill_md, regles_json, calcul_py.
     * Utilisé par le frontend pour afficher les boutons Smart Actions
     * et par n8n pour le routing optionnel.
     */
    public function index(Request $request)
    {
        $query = SkillsCatalog::active()
            ->select(['id', 'skill_id', 'nom', 'code', 'version', 'type', 'description', 'tags', 'priority'])
            ->orderBy('priority')
            ->orderBy('code');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->get());
    }

    /**
     * GET /api/v1/skills/{code}
     *
     * Charge un skill complet par son code (ex: "CNAV", "RACL").
     * Retourne skill_md + regles_json + calcul_py (Level 2+3).
     * Utilisé par n8n pour construire le prompt IA.
     * Case-insensitive : "cnav", "CNAV", "Cnav" fonctionnent tous.
     */
    public function showByCode(string $code)
    {
        $skill = SkillsCatalog::active()->byCode($code)->first();

        if (!$skill) {
            return response()->json([
                'error' => 'Skill not found or inactive',
                'code'  => strtoupper($code),
                'hint'  => 'Use GET /api/v1/skills to list available skills',
            ], 404);
        }

        return response()->json($skill);
    }

    /**
     * GET /api/v1/skills/id/{skillId}
     *
     * Charge un skill par son skill_id exact (ex: "SKILL_calcul_cnav_v1").
     * Utile quand le skill_id est stocké dans audit_log ou analysis_reports.
     */
    public function showBySkillId(string $skillId)
    {
        $skill = SkillsCatalog::active()->where('skill_id', $skillId)->first();

        if (!$skill) {
            return response()->json([
                'error'    => 'Skill not found or inactive',
                'skill_id' => $skillId,
            ], 404);
        }

        return response()->json($skill);
    }

    /**
     * PUT /api/v1/skills/{id}
     *
     * Met à jour UNIQUEMENT skill_md, description, active, priority, tags.
     * Tout autre champ (regles_json, calcul_py, code, skill_id, type, nom, version)
     * envoyé dans le body est ignoré. L'historique est créé automatiquement par le
     * hook boot() du modèle si skill_md change.
     *
     * Accès : admin uniquement.
     */
    public function update(Request $request, $id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        if ($auth->role !== 'admin') {
            return response()->json(['error' => 'Forbidden — admin only'], 403);
        }

        $skill = SkillsCatalog::find($id);

        if (!$skill) {
            return response()->json(['error' => 'Skill not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'skill_md'    => 'sometimes|required|string',
            'regles_json' => 'sometimes|nullable',
            'description' => 'nullable|string',
            'active'      => 'sometimes|boolean',
            'priority'    => 'sometimes|integer|min:0',
            'tags'        => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $request->only(['skill_md', 'regles_json', 'description', 'active', 'priority', 'tags']);

        $skill->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Skill mis à jour avec succès',
            'skill'   => $skill->fresh(),
        ]);
    }

    /**
     * POST /api/v1/skills
     *
     * Crée un nouveau skill. L'admin saisit regles_json et calcul_py en brut —
     * ils ne seront plus modifiables via l'endpoint update.
     *
     * Accès : admin uniquement.
     */
    public function store(Request $request)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        if ($auth->role !== 'admin') {
            return response()->json(['error' => 'Forbidden — admin only'], 403);
        }

        $validator = Validator::make($request->all(), [
            'skill_id'    => 'required|string|max:100|unique:skills_catalog,skill_id',
            'nom'         => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:skills_catalog,code',
            'type'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'skill_md'    => 'required|string',
            'regles_json' => 'required|array',
            'calcul_py'   => 'nullable|string',
            'tags'        => 'nullable|array',
            'priority'    => 'nullable|integer|min:0',
            'version'     => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $skill = SkillsCatalog::create([
            'skill_id'    => $request->skill_id,
            'nom'         => $request->nom,
            'code'        => strtoupper($request->code),
            'version'     => $request->version ?? '1.0',
            'type'        => $request->type,
            'description' => $request->description,
            'skill_md'    => $request->skill_md,
            'regles_json' => $request->regles_json,
            'calcul_py'   => $request->calcul_py,
            'tags'        => $request->tags,
            'priority'    => $request->priority ?? 5,
            'active'      => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Skill créé avec succès',
            'skill'   => $skill,
        ], 201);
    }

    /**
     * GET /api/v1/skills/{id}/history
     *
     * Renvoie les versions antérieures de skill_md pour un skill donné (desc).
     *
     * Accès : admin uniquement.
     */
    public function history($id)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        if ($auth->role !== 'admin') {
            return response()->json(['error' => 'Forbidden — admin only'], 403);
        }

        $skill = SkillsCatalog::find($id);

        if (!$skill) {
            return response()->json(['error' => 'Skill not found'], 404);
        }

        $history = $skill->history()->with('creator:id,name,email')->get();

        return response()->json($history);
    }

    /**
     * POST /api/v1/skills/{id}/restore/{version}
     *
     * Recharge skill_md depuis skills_catalog_history à la version demandée.
     * Le hook boot() du modèle crée automatiquement une nouvelle entrée
     * d'historique avec la valeur courante avant écrasement.
     *
     * Accès : admin uniquement.
     */
    public function restore($id, $version)
    {
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }

        if ($auth->role !== 'admin') {
            return response()->json(['error' => 'Forbidden — admin only'], 403);
        }

        $skill = SkillsCatalog::find($id);

        if (!$skill) {
            return response()->json(['error' => 'Skill not found'], 404);
        }

        $historyEntry = SkillsCatalogHistory::where('skills_catalog_id', $id)
            ->where('version', $version)
            ->first();

        if (!$historyEntry) {
            return response()->json(['error' => 'Version not found'], 404);
        }

        $skill->update([
            'skill_md' => $historyEntry->skill_md,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Version $version restaurée avec succès",
            'skill'   => $skill->fresh(),
        ]);
    }
}
