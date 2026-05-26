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
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY non configurée');
        }

        $model = config('services.gemini.model', 'gemini-2.0-flash');
        $baseUrl = rtrim(config('services.gemini.base_url'), '/');
        $timeout = (int) config('services.gemini.timeout', 120);

        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

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

        // Retry sur erreurs transitoires (503 overloaded, 429 quota, 500 server error)
        // 3 tentatives max avec backoff exponentiel : 2s, 4s, 8s
        $maxAttempts = 3;
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
            Log::warning('Gemini API transient error, retrying', [
                'status'   => $status,
                'attempt'  => $attempt,
                'next_in'  => $delaySeconds . 's',
            ]);
            sleep($delaySeconds);
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
