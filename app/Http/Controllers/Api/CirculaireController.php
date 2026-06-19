<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Circulaires\AgentLoader;
use App\Services\Circulaires\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CirculaireController extends Controller
{
    public function __construct(private AgentLoader $loader)
    {
    }

    /**
     * POST /api/v1/circulaires/select
     * Routage DÉTERMINISTE uniquement (aucun appel Gemini ici — c'est n8n qui appelle Gemini).
     * Body: { context: {...}, payload: {...} }  (payload = PREP PAYLOAD de n8n, context = BUILD CONTEXT)
     * Returns: { active_agents: [ { slug, name, prompt, circulaire_md, output } ], detail: {...} }
     *
     * n8n itère sur active_agents : un appel Gemini natif n8n par agent.
     */
    public function select(Request $request, Router $router): JsonResponse
    {
        $context = (array) $request->input('context', []);
        $payload = (array) $request->input('payload', []);
        $userCtx = $request->input('user_context');

        // Les triggers lisent des champs présents soit dans le payload (totaux,
        // scenarios_retenus, carriere) soit dans le context dérivé. On fusionne.
        $merged = array_merge($payload, $context);

        // useLlmFallback = false : routage 100% déterministe, pas de Gemini côté Laravel.
        $routing = $router->decide($merged, is_string($userCtx) ? $userCtx : null, false);

        $active = [];
        foreach ($routing['active'] as $slug) {
            $agent = $this->loader->loadOne($slug);
            if (!$agent) continue;
            $body = $this->loader->loadCirculaireBody($agent['circulaire']);
            if ($body === null) continue;
            $active[] = [
                'slug'          => $agent['slug'],
                'name'          => $agent['name'],
                'prompt'        => $agent['body'],
                'circulaire'    => $agent['circulaire'],
                'circulaire_md' => $body,
                'output'        => $agent['output'] ?? ['correction' => true, 'note' => true],
            ];
        }

        return response()->json([
            'active_agents' => $active,
            'detail'        => $routing['detail'],
        ]);
    }

    public function manifest(): JsonResponse
    {
        $agents = $this->loader->loadAll();
        $payload = array_values(array_map(static function (array $agent) {
            return [
                'slug'        => $agent['slug'],
                'name'        => $agent['name'],
                'circulaire'  => $agent['circulaire'],
                'triggers_js' => $agent['triggers_js'] ?? [],
                'llm_hint'    => $agent['llm_hint'] ?? '',
                'output'      => $agent['output'] ?? ['correction' => true, 'note' => true],
            ];
        }, $agents));

        return response()->json(['agents' => $payload]);
    }

    public function agent(string $slug): JsonResponse
    {
        $agent = $this->loader->loadOne($slug);
        if (!$agent) {
            return response()->json(['error' => 'Agent not found', 'slug' => $slug], 404);
        }
        $body = $this->loader->loadCirculaireBody($agent['circulaire']);
        if ($body === null) {
            return response()->json([
                'error' => 'Circulaire source file missing',
                'slug'  => $slug,
                'file'  => $agent['circulaire'],
            ], 500);
        }

        return response()->json([
            'slug'          => $agent['slug'],
            'name'          => $agent['name'],
            'prompt'        => $agent['body'],
            'circulaire'    => $agent['circulaire'],
            'circulaire_md' => $body,
            'output'        => $agent['output'] ?? ['correction' => true, 'note' => true],
        ]);
    }
}
