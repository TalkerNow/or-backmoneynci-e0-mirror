<?php

namespace App\Services\Circulaires;

use App\Services\GeminiClient;
use Illuminate\Support\Facades\Log;

class Router
{
    public function __construct(
        private AgentLoader $loader,
        private GeminiClient $gemini
    ) {}

    /**
     * Returns ['active' => [...slugs], 'detail' => ['fired'=>[], 'llm_fired'=>[], 'skipped'=>[]]]
     */
    public function decide(array $context, ?string $userContext, bool $useLlmFallback = true): array
    {
        $agents = $this->loader->loadAll();
        $fired = [];
        $skipped = [];
        $residuals = [];

        foreach ($agents as $slug => $agent) {
            if ($this->triggerFires($slug, $context)) {
                $fired[] = $slug;
            } elseif (!empty($agent['llm_hint'])) {
                $residuals[] = $slug;
            } else {
                $skipped[] = $slug;
            }
        }

        $llmFired = [];
        if ($useLlmFallback && !empty($residuals) && !empty(trim((string) $userContext))) {
            $llmFired = $this->llmRouter($residuals, $agents, $context, (string) $userContext);
        }

        $active = array_values(array_unique(array_merge($fired, $llmFired)));

        return [
            'active' => $active,
            'detail' => [
                'fired_deterministic' => $fired,
                'fired_llm'           => $llmFired,
                'skipped'             => array_values(array_diff($skipped, $llmFired)),
                'residuals_offered'   => $residuals,
            ],
        ];
    }

    /**
     * Triggers use ONLY fields present in the payload sent to n8n
     * (frozen_data / totaux / scenarios_retenus / carriere / calcul_json),
     * not BUILD CONTEXT derivatives which n8n never returns.
     */
    private function triggerFires(string $slug, array $c): bool
    {
        $totaux = $c['totaux'] ?? ($c['frozen_data']['totaux'] ?? []);
        $carriere = $c['carriere'] ?? ($c['frozen_data']['carriere'] ?? []);
        $scenariosRetenus = $c['scenarios_retenus'] ?? [];
        $calcul = $c['calcul_json'] ?? [];

        $hasScenarioVplr = function () use ($scenariosRetenus): bool {
            foreach ((array) $scenariosRetenus as $s) {
                if (strtoupper((string)($s['skill_code'] ?? '')) === 'VPLR') return true;
            }
            return false;
        };

        $hasRegimeAgircArrco = function () use ($carriere, $totaux): bool {
            if ((float)($totaux['agirc_points'] ?? 0) > 0) return true;
            foreach ((array) $carriere as $a) {
                $regimes = $a['regimes'] ?? [];
                if (isset($regimes['AGIRC-ARRCO']) || isset($regimes['ARRCO']) || isset($regimes['AGIRC'])) return true;
            }
            return false;
        };

        $hasRegimeRci = function () use ($carriere, $totaux): bool {
            if ((float)($totaux['rci_points'] ?? 0) > 0) return true;
            foreach ((array) $carriere as $a) {
                $regimes = $a['regimes'] ?? [];
                if (isset($regimes['RCI']) || isset($regimes['SSI']) || isset($regimes['RSI'])) return true;
            }
            return false;
        };

        return match ($slug) {
            'agirc-arrco'      => $hasRegimeAgircArrco(),
            'valeurs-2025'     => true,
            'ages-trimestres'  => true,
            'vplr'             => $hasScenarioVplr(),
            'racl-conditions'  => $hasScenarioVplr(),
            'racl-procedures'  => $hasScenarioVplr(),
            'rci'              => $hasRegimeRci(),
            'revalorisation'   => !empty($calcul) || !empty($totaux),
            default            => false,
        };
    }

    private function llmRouter(array $residualSlugs, array $agents, array $context, string $userContext): array
    {
        $candidates = [];
        foreach ($residualSlugs as $slug) {
            $candidates[] = [
                'slug'     => $slug,
                'name'     => $agents[$slug]['name'] ?? $slug,
                'llm_hint' => $agents[$slug]['llm_hint'] ?? '',
            ];
        }

        $ctxSummary = [
            'CLIENT_NOM'                => $context['CLIENT_NOM'] ?? null,
            'SCENARIOS_RETENUS_LABELS'  => $context['SCENARIOS_RETENUS_LABELS'] ?? null,
            'HAS_VFU'                   => $context['HAS_VFU'] ?? null,
            'HAS_MAJO_FAM'              => $context['HAS_MAJO_FAM'] ?? null,
            'ETRANGER_IMPACT'           => $context['ETRANGER_IMPACT'] ?? null,
        ];

        $system = 'Tu es un routeur. Tu reçois une liste d\'agents candidats et un contexte client. '
            . 'Retourne UNIQUEMENT un JSON {"fire":["slug1","slug2"]} listant les agents à activer (peut être vide). '
            . 'Pas de texte autour, pas de Markdown.';

        $user = "Agents candidats:\n" . json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nContexte client:\n" . json_encode($ctxSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nNote consultant:\n" . $userContext;

        try {
            $raw = $this->gemini->chat($system, [], $user);
        } catch (\Throwable $e) {
            Log::warning('Circulaires LLM router failed', ['err' => $e->getMessage()]);
            return [];
        }

        if (!preg_match('/\{[\s\S]*\}/', $raw, $m)) return [];
        $obj = json_decode($m[0], true);
        if (!is_array($obj) || !isset($obj['fire']) || !is_array($obj['fire'])) return [];

        $valid = array_intersect($obj['fire'], $residualSlugs);
        return array_values($valid);
    }
}
