<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimulatorChatSession;
use App\Models\SimulatorChatMessage;
use App\Models\User;
use Illuminate\Http\Request;

class SimulatorChatController extends Controller
{
    private function authUser()
    {
        try {
            return auth('api')->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            abort(401, 'Non authentifié.');
        }
    }

    // admin + consultant : accès complet à l'assistant retraite quel que soit le
    // client — aligné sur le chat rapport (aucune restriction) et sur l'affichage
    // de la barre côté front. Insensible à la casse (les rôles en base mélangent
    // admin/Admin, Consultant…). Les autres rôles (expert…) restent limités à
    // leurs clients assignés via le fallback parent_id ci-dessous.
    private function hasFullAssistantAccess($user): bool
    {
        return in_array(
            strtolower(trim((string) $user->role)),
            ['admin', 'consultant'],
            true
        );
    }

    private function canAccessCustomer($user, int $customerId): bool
    {
        if ($this->hasFullAssistantAccess($user)) return true;
        // sinon (expert, client…) : uniquement ses propres clients assignés
        $client = User::find($customerId);
        if (!$client) return false;
        return (int) $client->parent_id === (int) $user->id;
    }

    private function canAccessSession($user, SimulatorChatSession $session): bool
    {
        if ($this->hasFullAssistantAccess($user)) return true;
        if ((int) $session->user_id === (int) $user->id) return true;
        return $this->canAccessCustomer($user, (int) $session->customer_id);
    }

    public function listSessions(Request $request)
    {
        $user = $this->authUser();
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        if (!$this->canAccessCustomer($user, (int) $data['customer_id'])) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $sessions = SimulatorChatSession::where('customer_id', $data['customer_id'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($sessions);
    }

    public function createSession(Request $request)
    {
        $user = $this->authUser();
        $data = $request->validate(['customer_id' => ['required', 'integer']]);

        if (!$this->canAccessCustomer($user, (int) $data['customer_id'])) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $session = SimulatorChatSession::create([
            'user_id'      => $user->id,
            'customer_id'  => $data['customer_id'],
            'context_page' => 'simulateur_client',
        ]);

        return response()->json($session, 201);
    }

    public function getSession(Request $request, $id)
    {
        $user = $this->authUser();
        $session = SimulatorChatSession::findOrFail($id);
        if (!$this->canAccessSession($user, $session)) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }
        $messages = $session->messages()->get(['id', 'role', 'content', 'metadata', 'created_at']);
        return response()->json(array_merge($session->toArray(), ['messages' => $messages]));
    }

    public function deleteSession(Request $request, $id)
    {
        $user = $this->authUser();
        $session = SimulatorChatSession::findOrFail($id);
        if (!$this->canAccessSession($user, $session)) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }
        $session->delete();
        return response()->json(['deleted' => true]);
    }

    public function sendMessage(Request $request, $id)
    {
        $user = $this->authUser();
        $session = SimulatorChatSession::findOrFail($id);
        if (!$this->canAccessSession($user, $session)) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:16000'],
            'context' => ['nullable', 'array'],
        ]);

        $userMessage = SimulatorChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $data['content'],
        ]);

        $history = $session->messages()
            ->where('id', '<', $userMessage->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // Référentiel des régimes de retraite obligatoires (couche B, versionné dans resources/).
        // Donnée de référence vérifiée injectée dans CHAQUE requête n8n → le prompt la lit via
        // {{ $json.body.referentiel }} et l'agent répond aux questions « régimes » à partir d'elle.
        $referentiel = [];
        $refPath = resource_path('referentiels/referentiel_regimes.json');
        if (is_file($refPath)) {
            $referentiel = json_decode(file_get_contents($refPath), true) ?: [];
        }

        $payload = [
            'session_id'   => $session->id,
            'context_page' => $session->context_page,
            'customer_id'  => $session->customer_id,
            'user'         => [
                'id'   => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'role' => $user->role,
            ],
            'history'     => $history,
            'message'     => $data['content'],
            'context'     => $data['context'] ?? null,
            'referentiel' => $referentiel,
            'timestamp'   => now()->toIso8601String(),
        ];

        $webhookUrl = env('N8N_SIMULATOR_CHAT_WEBHOOK', '');
        $assistantContent = '';
        $metadata = [];

        if (!empty($webhookUrl)) {
            try {
                $client   = new \GuzzleHttp\Client(['timeout' => 60]);
                $response = $client->post($webhookUrl, ['json' => $payload]);
                $body     = json_decode($response->getBody()->getContents(), true);
                $assistantContent = $body['message'] ?? $body['content'] ?? json_encode($body);
                $metadata = [
                    'model'      => $body['model'] ?? null,
                    'tokens_in'  => $body['tokens_input'] ?? null,
                    'tokens_out' => $body['tokens_output'] ?? null,
                    'latency_ms' => $body['latency_ms'] ?? null,
                ];
            } catch (\Throwable $e) {
                $assistantContent = '[ERREUR] Impossible de joindre le workflow n8n : ' . $e->getMessage();
            }
        } else {
            $assistantContent = '[STUB] Message reçu : "' . $data['content'] . '". Workflow n8n non configuré.';
        }

        $assistantMessage = SimulatorChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'content'    => $assistantContent,
            'metadata'   => !empty($metadata) ? $metadata : null,
        ]);

        $session->touch();

        return response()->json([
            'user_message'      => $userMessage,
            'assistant_message' => $assistantMessage,
        ], 201);
    }
}
