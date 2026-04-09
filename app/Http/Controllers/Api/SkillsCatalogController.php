<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SkillsCatalog;
use Illuminate\Http\Request;

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
}
