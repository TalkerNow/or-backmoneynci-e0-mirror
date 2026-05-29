<?php

namespace App\Services\Circulaires;

/**
 * Sélection déterministe des agents Circulaires à embarquer dans un payload n8n.
 * AUCUN appel Gemini ici — c'est n8n qui appelle Gemini. On se contente de router
 * (Router déterministe) et de charger prompt + corps de circulaire pour chaque
 * agent retenu.
 *
 * Utilisé par SimulationRetraiteController et RapportConsultationController.
 */
class Selector
{
    public function __construct(
        private Router $router,
        private AgentLoader $loader
    ) {}

    /**
     * @return array<int,array{slug:string,name:string,prompt:string,circulaire_md:string}>
     */
    public function selectAgents(array $context, ?string $userContext = null): array
    {
        $routing = $this->router->decide($context, $userContext, false); // false = pas de fallback LLM
        $out = [];
        foreach ($routing['active'] as $slug) {
            $agent = $this->loader->loadOne($slug);
            if (!$agent) continue;
            $body = $this->loader->loadCirculaireBody($agent['circulaire']);
            if ($body === null) continue;
            $out[] = [
                'slug'          => $agent['slug'],
                'name'          => $agent['name'],
                'prompt'        => $agent['body'],
                'circulaire_md' => $body,
            ];
        }
        return $out;
    }
}
