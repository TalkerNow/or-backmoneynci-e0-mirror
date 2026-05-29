<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Wrapper minimal autour de l'API Gemini (generativelanguage.googleapis.com).
 *
 * Méthode principale : chat(systemPrompt, history, userMessage) -> string
 *
 * L'API REST de Gemini attend un payload de la forme :
 *   {
 *     "system_instruction": { "parts": [ { "text": "..." } ] },
 *     "contents": [
 *        { "role": "user",  "parts": [ { "text": "..." } ] },
 *        { "role": "model", "parts": [ { "text": "..." } ] },
 *        ...
 *     ]
 *   }
 *
 * L'historique passé doit être un tableau de [ ['role' => 'user'|'assistant', 'content' => '...'], ... ].
 * Le rôle "assistant" est mappé vers "model" (terminologie Gemini).
 */
class GeminiClient
{
    public function chat(string $systemPrompt, array $history, string $userMessage): string
    {
        $contents = [];
        foreach ($history as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => (string) ($msg['content'] ?? '')]],
            ];
        }
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 65536,
            ],
        ];

        return $this->generateContent($payload);
    }

    /**
     * Envoie un PDF (base64) + une consigne et récupère la réponse de Gemini.
     *
     * Quand $responseSchema est fourni, on force une sortie JSON conforme au
     * schéma (responseMimeType + responseSchema) : la chaîne retournée est alors
     * du JSON directement décodable, sans regex de parsing.
     *
     * @param string     $pdfBase64      contenu du PDF encodé base64 (sans préfixe data:)
     * @param array|null $responseSchema schéma JSON Gemini (OpenAPI subset) ; null = texte libre
     * @param int|null   $timeout        timeout HTTP en secondes ; null = config par défaut.
     *                                   L'extraction d'un PDF multi-pages dépasse souvent les
     *                                   120 s du chat — passer une valeur plus large.
     */
    public function generateFromPdf(
        string $systemPrompt,
        string $userMessage,
        string $pdfBase64,
        ?array $responseSchema = null,
        ?int $timeout = null
    ): string {
        $generationConfig = [
            // Extraction réglementaire : on veut le déterminisme maximal, pas de créativité.
            'temperature'     => 0.0,
            'maxOutputTokens' => 65536,
        ];
        if ($responseSchema !== null) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema']   = $responseSchema;
        }

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'contents' => [[
                'role'  => 'user',
                'parts' => [
                    ['inline_data' => ['mime_type' => 'application/pdf', 'data' => $pdfBase64]],
                    ['text' => $userMessage],
                ],
            ]],
            'generationConfig' => $generationConfig,
        ];

        return $this->generateContent($payload, $timeout);
    }

    /**
     * POST bas niveau vers generateContent avec retry sur erreurs transitoires
     * (503 overloaded, 429 quota, 5xx) — 5 tentatives, backoff 2/4/8/16s + jitter.
     * Retourne le texte du premier candidat.
     *
     * @param int|null $timeout timeout HTTP en secondes ; null = config par défaut.
     */
    private function generateContent(array $payload, ?int $timeout = null): string
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY non configurée');
        }

        $model   = config('services.gemini.model', 'gemini-2.0-flash');
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $timeout = $timeout ?? (int) config('services.gemini.timeout', 120);

        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

        // 5 tentatives max avec backoff exponentiel 2/4/8/16 s + jitter (0-1000 ms).
        // Absorbe les pics de surcharge Gemini (HTTP 503 "model is overloaded"),
        // fréquents même sur les modèles GA aux heures de pointe.
        $maxAttempts = 5;
        $response = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                break;
            }

            $status = $response->status();
            $isRetryable = in_array($status, [429, 500, 502, 503, 504], true);

            if (!$isRetryable || $attempt === $maxAttempts) {
                Log::error('Gemini API error', [
                    'status'   => $status,
                    'body'     => $response->body(),
                    'attempts' => $attempt,
                ]);
                throw new RuntimeException('Erreur Gemini : HTTP ' . $status);
            }

            $delaySeconds = (int) pow(2, $attempt);
            $jitterMs     = random_int(0, 1000);
            Log::warning('Gemini API transient error, retrying', [
                'status'   => $status,
                'attempt'  => $attempt,
                'next_in'  => $delaySeconds . 's (+' . $jitterMs . 'ms)',
            ]);
            usleep($delaySeconds * 1_000_000 + $jitterMs * 1000);
        }

        $json = $response->json();
        $text = data_get($json, 'candidates.0.content.parts.0.text');

        if (!is_string($text) || $text === '') {
            Log::warning('Gemini returned empty text', ['response' => $json]);
            throw new RuntimeException('Gemini a retourné une réponse vide');
        }

        return $text;
    }
}
