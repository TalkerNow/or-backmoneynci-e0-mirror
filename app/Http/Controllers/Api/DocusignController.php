<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\Documents;
use App\Models\SignatureRequest;

use DocuSign\eSign\Configuration;
use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Api\EnvelopesApi;

use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\TemplateRole;
use DocuSign\eSign\Model\Tabs;
use DocuSign\eSign\Model\Text;
use DocuSign\eSign\Model\CustomFields;
use DocuSign\eSign\Model\EventNotification;
use DocuSign\eSign\Model\EnvelopeEvent;
use DocuSign\eSign\Model\RecipientViewRequest;

use DocuSign\eSign\Client\ApiException as DSEApiException;
use DocuSign\eSign\Client\Auth\OAuth;
use DocuSign\eSign\ObjectSerializer;

use Firebase\JWT\JWT;
use Exception;

class DocusignController extends Controller
{
    // OAuth scopes
    public static $SCOPE_SIGNATURE     = "signature";
    public static $SCOPE_IMPERSONATION = "impersonation";
    public static $GRANT_TYPE_JWT      = "urn:ietf:params:oauth:grant-type:jwt-bearer";

    public function __construct(Configuration $config = null, OAuth $oAuth = null)
    {
        // Le Connect callback ne doit PAS exiger d'auth applicative
        $this->middleware('auth:api', ['except' => ['docusignConnectCallback']]);
    }

    /* -----------------------------------------------------------
     | Utilitaires
     * ----------------------------------------------------------*/
    private function create_fullname($first_name, $last_name)
    {
        return trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
    }

    private function create_full_address($adr, $zip, $city, $country)
    {
        return trim(($adr ?? '') . " " . ($zip ?? '') . " " . ($city ?? '') . " " . ($country ?? ''));
    }

    private function safeFileSuffix($s)
    {
        return preg_replace('/[^0-9A-Za-z_\-]/', '-', (string)$s);
    }

    /* -----------------------------------------------------------
     | Auth DocuSign (JWT)
     * ----------------------------------------------------------*/
    public function requestJWTApplicationToken($client_id, $rsa_private_key, $scopes = null, $expires_in = 60)
    {
        if (!$client_id) {
            throw new \InvalidArgumentException('Missing DOCUSIGN_CLIENT_ID');
        }
        if (!$rsa_private_key) {
            throw new \InvalidArgumentException('Missing DOCUSIGN_KEY_PRIVATE');
        }

        $scopes = $scopes ?: self::$SCOPE_SIGNATURE . ' ' . self::$SCOPE_IMPERSONATION;
        if ((int)$expires_in > 60) $expires_in = 60;

        $now = time();
        $claim = [
            "iss"   => $client_id,
            "sub"   => env('DOCUSIGN_USER_ID'),       // GUID de l’utilisateur API (consent donné)
            "aud"   => env('DOCUSIGN_BASE_PATH'),     // ex: account-d.docusign.com
            "iat"   => $now,
            "exp"   => $now + (int)$expires_in * 60,
            "scope" => is_array($scopes) ? implode(' ', $scopes) : $scopes
        ];

        $jwt  = JWT::encode($claim, $rsa_private_key, 'RS256');
        $url  = 'https://' . env('DOCUSIGN_BASE_PATH') . '/oauth/token';
        $ch   = curl_init();
        $body = http_build_query([
            'assertion'  => $jwt,
            'grant_type' => self::$GRANT_TYPE_JWT
        ]);
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('DocuSign OAuth call failed: ' . curl_error($ch));
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($resp, true);
        if ($code < 200 || $code >= 300 || !isset($json['access_token'])) {
            throw new Exception('DocuSign OAuth error: ' . $resp);
        }
        return $json['access_token'];
    }

    private function dsClient(): EnvelopesApi
    {
        $config = new Configuration();
        $config->setHost(env('DOCUSIGN_API_PATH')); // ex: https://demo.docusign.net/restapi
        $token = $this->requestJWTApplicationToken(env('DOCUSIGN_CLIENT_ID'), env('DOCUSIGN_KEY_PRIVATE'));
        $config->addDefaultHeader('Authorization', 'Bearer ' . $token);
        $apiClient = new ApiClient($config);
        return new EnvelopesApi($apiClient);
    }

    /* -----------------------------------------------------------
     | ENVELOPE: Procuration (OptionRetraite)
     * ----------------------------------------------------------*/
    /**
     * ATTENTION : ton template DocuSign doit avoir le rôle **Client**
     * et les TextTabs suivants (labels exactement identiques) :
     * - principal_full_name
     * - principal_full_address
     * - principal_phone
     * - principal_email
     * - agent_full_name
     * - agent_full_address
     * - agent_phone
     * - agent_email
     * - procuration_scope
     * - procuration_start_date
     * - procuration_end_date
     */
    private function make_procuration_envelope(array $user, array $procu, string $template_id, bool $embedded = true): EnvelopeDefinition
    {
        $tabs = new Tabs([
            'text_tabs' => [
                new Text(['tab_label' => 'principal_full_name',   'value' => $this->create_fullname($user['first_name'] ?? '', $user['last_name'] ?? '')]),
                new Text(['tab_label' => 'principal_full_address','value' => $this->create_full_address($user['adr'] ?? '', $user['zip'] ?? '', $user['city'] ?? '', $user['country'] ?? '')]),
                new Text(['tab_label' => 'principal_phone',       'value' => (string)($user['phone'] ?? '')]),
                new Text(['tab_label' => 'principal_email',       'value' => (string)($user['email'] ?? '')]),

                new Text(['tab_label' => 'agent_full_name',       'value' => (string)($procu['agent_full_name'] ?? '')]),
                new Text(['tab_label' => 'agent_full_address',    'value' => (string)($procu['agent_full_address'] ?? '')]),
                new Text(['tab_label' => 'agent_phone',           'value' => (string)($procu['agent_phone'] ?? '')]),
                new Text(['tab_label' => 'agent_email',           'value' => (string)($procu['agent_email'] ?? '')]),

                new Text(['tab_label' => 'procuration_scope',     'value' => (string)($procu['procuration_scope'] ?? '')]),
                new Text(['tab_label' => 'procuration_start_date','value' => (string)($procu['procuration_start_date'] ?? '')]),
                new Text(['tab_label' => 'procuration_end_date',  'value' => (string)($procu['procuration_end_date'] ?? '')]),
            ]
        ]);

        $clientUserId = (string)($user['id']); // pour signature embarquée

        $signer = new TemplateRole([
            'role_name'  => 'Client',
            'email'      => (string)$user['email'],
            'name'       => $this->create_fullname($user['first_name'] ?? '', $user['last_name'] ?? ''),
            'tabs'       => $tabs,
        ]);

        if ($embedded) {
            // Active la signature embarquée
            $signer['client_user_id']            = $clientUserId;
            $signer['embeddedRecipientStartURL'] = 'SIGN_AT_DOCUSIGN';
        }

        $envelope = new EnvelopeDefinition([
            'status'      => 'sent',
            'template_id' => $template_id,
            'template_roles' => [$signer],
        ]);

        // Custom fields pour typer le document côté callback
        $envelope->setCustomFields(new CustomFields([
            'text_custom_fields' => [
                ['name' => 'documentType', 'value' => 'procuration'],
                ['name' => 'templateID',   'value' => $template_id],
            ]
        ]));

        // Webhook Connect (événements)
        $eventNotification = new EventNotification();
        $eventNotification->setUrl(env('DOCUSIGN_CONNECT_REDIRECT_URL'));
        $eventNotification->setRequireAcknowledgment('true');
        $eventNotification->setIncludeDocuments('true');
        $eventNotification->setLoggingEnabled('true');
        $eventNotification->setEnvelopeEvents([
            (new EnvelopeEvent())
                ->setEnvelopeEventStatusCode('completed')
                ->setIncludeDocuments('true')
        ]);
        $envelope->setEventNotification($eventNotification);

        return $envelope;
    }

    /* -----------------------------------------------------------
     | API: Créer l’enveloppe de procuration
     * ----------------------------------------------------------*/
    public function requestSignature(Request $request)
    {
        // Auth API requise (admin/consultant par défaut)
        try {
            $auth = auth()->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if (!in_array($auth->role, ['admin', 'consultant'])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rules = [
            'kind'  => [ 'required', Rule::in(['procuration']) ],
            'user_id' => 'required|integer',

            // Champs de procuration (tous optionnels ici, DocuSign peut préremplir vide)
            'agent_full_name'        => 'nullable|string',
            'agent_full_address'     => 'nullable|string',
            'agent_phone'            => 'nullable|string',
            'agent_email'            => 'nullable|email',
            'procuration_scope'      => 'nullable|string',
            'procuration_start_date' => 'nullable|string',
            'procuration_end_date'   => 'nullable|string',

            // Signature embarquée ?
            'embedded'               => 'sometimes|boolean',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 422);
        }

        // Récupération utilisateur
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Normalise les infos nécessaires pour la tab mapping
        $userData = [
            'id'        => $user->id,
            'email'     => $user->email,
            'first_name'=> $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'adr'       => $user->personal_adr ?? $user->address ?? '',
            'zip'       => $user->personal_zip ?? $user->zip ?? '',
            'city'      => $user->personal_city ?? $user->city ?? '',
            'country'   => $user->personal_country ?? $user->country ?? '',
            'phone'     => $user->phone ?? '',
        ];

        $procu = [
            'agent_full_name'        => $request->agent_full_name,
            'agent_full_address'     => $request->agent_full_address,
            'agent_phone'            => $request->agent_phone,
            'agent_email'            => $request->agent_email,
            'procuration_scope'      => $request->procuration_scope,
            'procuration_start_date' => $request->procuration_start_date,
            'procuration_end_date'   => $request->procuration_end_date,
        ];

        $embedded   = (bool)$request->get('embedded', true);
        $templateId = '7658d6c0-c749-49ac-8693-f7c9815261b1';
        if (!$templateId) {
            return response()->json(['error' => 'Missing DOCUSIGN_PROCURATION_TEMPLATE_ID'], 500);
        }

        try {
            $api   = $this->dsClient();
            $env   = $this->make_procuration_envelope($userData, $procu, $templateId, $embedded);
            $res   = $api->createEnvelope(env('DOCUSIGN_ACCOUNT_ID'), $env);
            $envId = $res->getEnvelopeId();

            if (!$envId) {
                return response()->json(['error' => 'Unable to create envelope'], 400);
            }

            // Optionnel : lien de signature embarquée
            $signingUrl = null;
            if ($embedded) {
                try {
                    $viewReq = new RecipientViewRequest([
                        'authentication_method' => 'none',
                        'client_user_id'        => (string)$userData['id'],
                        'email'                 => $userData['email'],
                        'user_name'             => $this->create_fullname($userData['first_name'], $userData['last_name']),
                        'return_url'            => rtrim(env('APP_URL'), '/').'/docusign-return?envelopeId='.$envId,
                    ]);
                    $viewRes   = $api->createRecipientView(env('DOCUSIGN_ACCOUNT_ID'), $envId, $viewReq);
                    $signingUrl = $viewRes->getUrl();
                } catch (DSEApiException $e) {
                    Log::warning('createRecipientView failed: '.$e->getMessage());
                }
            }

            // (optionnel) stocker une SignatureRequest si tu en utilises
            try {
                SignatureRequest::create([
                    'user_id'     => $user->id,
                    'envelope_id' => $envId,
                    'type'        => 'procuration',
                    'meta'        => json_encode($procu),
                ]);
            } catch (\Throwable $e) {
                Log::warning('SignatureRequest create failed: '.$e->getMessage());
            }

            return response()->json([
                'success'      => true,
                'envelope_id'  => $envId,
                'signing_url'  => $signingUrl, // null si non embarquée
            ], 200);
        } catch (\Throwable $e) {
            Log::error('requestSignature error: '.$e->getMessage());
            return response()->json(['error' => 'DocuSign error', 'detail' => $e->getMessage()], 500);
        }
    }

    /* -----------------------------------------------------------
     | API: Générer un lien de signature embarquée (si besoin)
     * ----------------------------------------------------------*/
    public function getSigningLink(Request $request)
    {
        $request->validate([
            'envelope_id' => 'required|string',
            'user_id'     => 'required|integer',
            'return_url'  => 'sometimes|url'
        ]);

        $user = User::find($request->user_id);
        if (!$user) return response()->json(['error' => 'User not found'], 404);

        try {
            $api       = $this->dsClient();
            $accountId = env('DOCUSIGN_ACCOUNT_ID');
            $envelopeId= $request->envelope_id;
            $clientId  = (string)$user->id;

            // S’assure que le signer est bien "embedded"
            $recips = $api->listRecipients($accountId, $envelopeId);
            $signer = null;
            foreach (($recips->getSigners() ?? []) as $s) {
                if (strtolower($s->getEmail()) === strtolower($user->email)) {
                    $signer = $s; break;
                }
            }
            if (!$signer) {
                return response()->json(['error' => 'Signer not found'], 400);
            }
            if (!$signer->getClientUserId()) {
                $signer->setClientUserId($clientId);
                $api->updateRecipients($accountId, $envelopeId, new \DocuSign\eSign\Model\Recipients(['signers'=>[$signer]]));
            }

            $viewReq = new RecipientViewRequest([
                'authentication_method' => 'none',
                'client_user_id'        => $clientId,
                'email'                 => $user->email,
                'user_name'             => $this->create_fullname($user->first_name ?? '', $user->last_name ?? ''),
                'return_url'            => $request->get('return_url', rtrim(env('APP_URL'), '/').'/docusign-return?envelopeId='.$envelopeId),
            ]);
            $viewRes = $api->createRecipientView($accountId, $envelopeId, $viewReq);

            return response()->json([
                'success'     => true,
                'signing_url' => $viewRes->getUrl()
            ]);
        } catch (DSEApiException $e) {
            return response()->json(['error' => 'Unable to create view', 'detail' => $e->getMessage()], 400);
        }
    }

    /* -----------------------------------------------------------
     | Webhook Connect: réception & sauvegarde du PDF signé
     * ----------------------------------------------------------*/
    public function docusignConnectCallback(Request $request)
    {
        // 0) Vérif HMAC (facultative mais recommandée)
        $connectKey = env('DOCUSIGN_CONNECT_KEY'); // "Key" configurée dans Connect
        if ($connectKey) {
            $sigHeader = $request->header('X-DocuSign-Signature-1');
            $rawBody   = $request->getContent();
            $calc      = base64_encode(hash_hmac('sha256', $rawBody, $connectKey, true));
            if (!$sigHeader || !hash_equals($calc, $sigHeader)) {
                Log::warning('Invalid DocuSign HMAC');
                return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
            }
        }

        // 1) Payload (support à la fois {data:{envelopeSummary}} et {envelopeSummary})
        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return response()->json(['success' => false, 'error' => 'Invalid JSON'], 422);
        }

        $summary = $payload['data']['envelopeSummary'] ?? $payload['envelopeSummary'] ?? null;
        if (!$summary) {
            return response()->json(['success' => false, 'error' => 'Missing envelopeSummary'], 422);
        }

        $envelopeId = $summary['envelopeId'] ?? $summary['envelopeID'] ?? null;
        $status     = strtolower($summary['status'] ?? '');

        // 2) Si completed → nettoyer SignatureRequest
        if ($envelopeId && $status === 'completed') {
            try {
                SignatureRequest::where('envelope_id', $envelopeId)->delete();
            } catch (\Throwable $e) {
                Log::warning('SignatureRequest cleanup failed: '.$e->getMessage());
            }
        }

        $customFields = $summary['customFields']['textCustomFields'] ?? [];
        // Récupère documentType de manière robuste
        $docType = null;
        foreach ($customFields as $cf) {
            if (($cf['name'] ?? '') === 'documentType') {
                $docType = $cf['value'] ?? null;
                break;
            }
        }

        $signers   = $summary['recipients']['signers'] ?? [];
        $email     = $signers[0]['email'] ?? null; // si un seul signer (Client)
        $documents = $summary['envelopeDocuments'] ?? [];
        $pdfBytes  = $documents[0]['PDFBytes'] ?? null; // 1er doc

        if (!$email || !$pdfBytes || !$docType) {
            return response()->json(['success' => false, 'error' => 'Missing email/pdf/docType'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        // 3) Stockage
        try {
            $file   = base64_decode($pdfBytes);
            $userId = (string)$user->id;
            $time   = $this->safeFileSuffix($summary['completedDateTime'] ?? now()->toIso8601String());

            if ($docType === 'procuration') {
                Storage::disk('users')->makeDirectory("$userId/contract/procuration");
                $storedPath = "$userId/contract/procuration/procuration-$time.pdf";

                // Idempotence : si le fichier existe, ne recrée pas
                if (!Storage::disk('users')->exists($storedPath)) {
                    Storage::disk('users')->put($storedPath, $file);
                }

                // Évite doublons en DB
                $exists = Documents::where([
                    'user_id' => $userId,
                    'type'    => 'procuration',
                    'link_to_doc' => $storedPath,
                ])->exists();

                if (!$exists) {
                    Documents::create([
                        'user_id'     => $userId,
                        'link_to_doc' => $storedPath,
                        'title'       => basename($storedPath),
                        'type'        => 'procuration',
                        'is_approuved'=> true,
                    ]);
                }

                // Envoi email (facultatif mais utile)
                // $this->sendSignedDocMail($user, $storedPath, 'procuration');
            } else {
                Log::info("Doc type non géré: $docType");
                return response()->json(['success' => false, 'error' => 'Unhandled documentType'], 422);
            }
        } catch (\Throwable $e) {
            Log::error('Connect store error: '.$e->getMessage());
            return response()->json(['error' => "Couldn't store file"], 422);
        }

        return response()->json(['success' => true], 200);
    }

    /* -----------------------------------------------------------
     | Email avec PDF en PJ
     * ----------------------------------------------------------*/
    private function sendSignedDocMail(User $user, string $relativePath, string $docType): bool
    {
        $locale  = app()->getLocale();
        $view    = $locale === 'fr' ? 'mails.signed-doc' : 'mails.signed-doc-en';
        $subject = $locale === 'fr' ? 'Votre document signé est prêt' : 'Your signed document is ready';

        try {
            Mail::send(
                $view,
                [
                    'name'    => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                    'docType' => $docType,
                ],
                function ($message) use ($user, $subject, $relativePath) {
                    $message->to($user->email)
                            ->from(env('MAIL_FROM_ADDRESS'), env('APP_NAME'))
                            ->subject($subject)
                            ->attach(
                                Storage::disk('users')->path($relativePath),
                                ['as' => basename($relativePath), 'mime' => 'application/pdf']
                            );
                }
            );
            return true;
        } catch (\Throwable $ex) {
            Log::error('Mail sending failed: '.$ex->getMessage());
            return false;
        }
    }
}
