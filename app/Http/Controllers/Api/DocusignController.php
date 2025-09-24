<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\Documents;

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
use Illuminate\Support\Facades\Http;

use DocuSign\eSign\Model\Document as DSDocument;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\DateSigned;
use DocuSign\eSign\Client\ApiException as DSEApiException;
use DocuSign\eSign\Client\Auth\OAuth;

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

    private function splitChars(string $s, int $n): array {
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while (count($chars) < $n) $chars[] = '';
        return array_slice($chars, 0, $n);
    }

    private function onlyDigits(?string $s): string {
        return preg_replace('/\D+/', '', (string)$s);
    }

    private function dobToJJMMYYYY(?string $birthDate): string {
        // Tolérant: gère YYYY-MM-DD, YYYY/MM/DD, YYYYMMDD, avec ou sans heure
        $s = trim((string)$birthDate);
        if ($s === '') return '';
        if (preg_match('/(\d{4})\D?(\d{2})\D?(\d{2})/', $s, $m)) {
            $yyyy = $m[1]; $mm = $m[2]; $dd = $m[3];
            return $dd.$mm.$yyyy; // JJMMYYYY
        }
        // Fallback strict 8 chiffres
        $d = preg_replace('/\D+/', '', $s);
        if (strlen($d) === 8) {
            $yyyy = substr($d, 0, 4);
            $mm   = substr($d, 4, 2);
            $dd   = substr($d, 6, 2);
            return $dd.$mm.$yyyy;
        }
        return '';
    }

    private function computeContractTotals(array $v): array
    {
        $num = fn($k)=> (float)($v[$k] ?? 0);
        $int = fn($k)=> (int)($v[$k] ?? 0);
        $bool= fn($k)=> (bool)($v[$k] ?? false);

        $TVAP = $num('TVAP') ?: 20;
        $VTA  = 1 + $TVAP/100;

        // S1
        $nbHT1 = 0;
        if ($bool('c1')) {
            $nbHT1 = (int) floor( (($num('nb1-price') ?: 0) / 60) * $int('nb1') );
        }
        $TTC1 = $nbHT1 * $VTA;

        // S2
        $HT2   = $bool('c2') ? $num('p2') : 0;
        $nbHT2 = ($bool('c2') && $bool('cnb2')) ? (($num('nb2-price') ?: 0) * $num('nb2')) : 0;
        $TTC2  = ($HT2 + $nbHT2) * $VTA;

        // S3 & S4
        $HT3  = $bool('c3') ? $num('p3') : 0;
        $HT4  = $bool('c4') ? $num('p4') : 0;
        $TTC4 = ($HT3 + $HT4) * $VTA;
        $nbHT4= $bool('cnb4') ? (($num('nb4-price') ?: 0) * $num('nb4')) : 0;
        $TTC34= ($HT3 + $HT4 + $nbHT4) * $VTA;

        // S5
        $HT5   = ($bool('c5') && !$bool('cc5')) ? $num('p5') : 0;
        $nbHT5 = ($bool('c5') && $bool('cnb5')) ? (($num('nb5-price') ?: 0) * $num('nb5')) : 0;
        $TTC5  = ($HT5 + $nbHT5) * $VTA;

        // S6 & S7
        $HT6  = $bool('c6') ? $num('p6') : 0;
        $TTC6 = $HT6 * $VTA;
        $HT7  = $bool('c7') ? $num('p7') : 0;
        $TTC7 = $HT7 * $VTA;

        $TOTALHT = (int) floor($nbHT1 + $HT2 + $nbHT2 + $HT3 + $HT4 + $nbHT4 + $HT5 + $nbHT5 + $HT6 + $HT7);
        $TVA     = (float) round($TOTALHT * $TVAP / 100, 2);
        $TOTALTTC= (int) floor($TOTALHT * $VTA);

        $fp1 = max(0, min(100, (float)($v['fp1'] ?? 75)));
        $fp2 = 100 - $fp1;

        $FINAL75 = (int) floor($TOTALTTC * ($fp1/100)) . ".00";
        $FINAL25 = (int) floor($TOTALTTC * ($fp2/100)) . ".00";

        return array_merge($v, compact(
            'nbHT1','TTC1','HT2','nbHT2','TTC2','HT3','HT4','TTC4','nbHT4','TTC34',
            'HT5','nbHT5','TTC5','HT6','TTC6','HT7','TTC7',
            'TOTALHT','TVAP','TVA','TOTALTTC','fp1','fp2','FINAL75','FINAL25'
        ));
    }

    private function signerNameFromPI(array $pi, array $userData): string {
        $first = trim($pi['first_name'] ?? '');
        $usage = trim($pi['usage_last_name'] ?? '');
        if ($first !== '' || $usage !== '') return trim($first.' '.$usage);

        $first = trim($userData['first_name'] ?? '');
        $last  = trim($userData['last_name'] ?? '');
        if ($first !== '' || $last !== '') return trim($first.' '.$last);

        if (!empty($userData['name'])) return trim($userData['name']);
        if (!empty($userData['email'])) return explode('@', $userData['email'])[0];
        return 'Client OptionRetraite';
    }

    private function fallbackSignerName(array $user): string
    {
        $full = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''));
        if ($full !== '') return $full;

        if (!empty($user['name'])) {
            return trim((string)$user['name']);
        }

        if (!empty($user['email'])) {
            $local = explode('@', $user->email)[0] ?? null;
            if ($local) return $local;
        }

        return 'Client OptionRetraite';
    }

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

    /**
     * Pick la première propriété non vide parmi une liste d'alias sur un objet (row Eloquent/StdClass).
     */
    private function pick($row, array $candidates, $default = null) {
        foreach ($candidates as $c) {
            if ($row && isset($row->$c) && $row->$c !== '' && $row->$c !== null) {
                return $row->$c;
            }
        }
        return $default;
    }

    /**
     * Pick la première clé non vide depuis la Request (payload).
     */
    private function pickInput(Request $r, array $keys, $default = null) {
        foreach ($keys as $k) {
            $v = $r->input($k);
            if ($v !== null && $v !== '') return $v;
        }
        return $default;
    }

    /* -----------------------------------------------------------
     | Auth DocuSign (JWT) — via config('services.docusign.*')
     * ----------------------------------------------------------*/

    public function requestJWTApplicationToken(
        string $client_id,
        string $user_id,
        string $base_path,
        string $rsa_private_key,
        $scopes = null,
        int $expires_in = 60
    ) {
        if (!$client_id)  throw new \InvalidArgumentException('Missing DOCUSIGN_CLIENT_ID (config services.docusign.client_id)');
        if (!$user_id)    throw new \InvalidArgumentException('Missing DOCUSIGN_USER_ID (config services.docusign.user_id)');
        if (!$base_path)  throw new \InvalidArgumentException('Missing DOCUSIGN_BASE_PATH (config services.docusign.base_path)');
        if (!$rsa_private_key) throw new \InvalidArgumentException('Missing DOCUSIGN_KEY_PRIVATE (config services.docusign.private_key)');

        $scopes = $scopes ?: self::$SCOPE_SIGNATURE . ' ' . self::$SCOPE_IMPERSONATION;
        if ($expires_in > 60) $expires_in = 60;

        $now = time();
        $claim = [
            "iss"   => $client_id,
            "sub"   => $user_id,
            "aud"   => $base_path, // ex: account.docusign.com
            "iat"   => $now,
            "exp"   => $now + ($expires_in * 60),
            "scope" => is_array($scopes) ? implode(' ', $scopes) : $scopes
        ];

        $jwt  = JWT::encode($claim, $rsa_private_key, 'RS256');
        $url  = 'https://' . $base_path . '/oauth/token';

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
        $apiPath    = (string) config('services.docusign.api_path');
        $clientId   = (string) config('services.docusign.client_id');
        $userId     = (string) config('services.docusign.user_id');
        $basePath   = (string) config('services.docusign.base_path');
        $privateKey = (string) config('services.docusign.private_key');

        $config = new Configuration();
        $config->setHost($apiPath);

        $token = $this->requestJWTApplicationToken($clientId, $userId, $basePath, $privateKey);
        $config->addDefaultHeader('Authorization', 'Bearer ' . $token);

        $apiClient = new ApiClient($config);
        return new EnvelopesApi($apiClient);
    }

    /* -----------------------------------------------------------
     | ENVELOPE: Procuration (OptionRetraite)
     * ----------------------------------------------------------*/
    /**
     * Ton template DocuSign doit avoir le rôle **Client** et ces TextTabs :
     * principal_full_name, principal_full_address, principal_phone, principal_email,
     * agent_full_name, agent_full_address, agent_phone, agent_email,
     * procuration_scope, procuration_start_date, procuration_end_date
     *
     * + ceux que tu as ajoutés :
     * birth_last_name, usage_last_name, first_name,
     * dob_1..dob_8, nir_1..nir_15, full_address
     */
    private function make_procuration_envelope(
        array $user,
        array $procu,
        string $template_id,
        bool $embedded = true,
        array $extraTextTabs = [],          // tabs PI en plus
        ?string $forcedSignerName = null    // nom imposé
    ): EnvelopeDefinition
    {
        // Tabs historiques (principal_*, agent_*, etc.)
        $baseTabs = [
            new Text(['tab_label' => 'principal_full_name',    'value' => $this->create_fullname($user['first_name'] ?? '', $user['last_name'] ?? '')]),
            new Text(['tab_label' => 'principal_full_address', 'value' => $this->create_full_address($user['adr'] ?? '', $user['zip'] ?? '', $user['city'] ?? '', $user['country'] ?? '')]),
            new Text(['tab_label' => 'principal_phone',        'value' => (string)($user['phone'] ?? '')]),
            new Text(['tab_label' => 'principal_email',        'value' => (string)($user['email'] ?? '')]),

            new Text(['tab_label' => 'agent_full_name',        'value' => (string)($procu['agent_full_name'] ?? '')]),
            new Text(['tab_label' => 'agent_full_address',     'value' => (string)($procu['agent_full_address'] ?? '')]),
            new Text(['tab_label' => 'agent_phone',            'value' => (string)($procu['agent_phone'] ?? '')]),
            new Text(['tab_label' => 'agent_email',            'value' => (string)($procu['agent_email'] ?? '')]),

            new Text(['tab_label' => 'procuration_scope',      'value' => (string)($procu['procuration_scope'] ?? '')]),
            new Text(['tab_label' => 'procuration_start_date', 'value' => (string)($procu['procuration_start_date'] ?? '')]),
            new Text(['tab_label' => 'procuration_end_date',   'value' => (string)($procu['procuration_end_date'] ?? '')]),
        ];

        // Merge avec les tabs PI
        $tabs = new Tabs(['text_tabs' => array_merge($baseTabs, $extraTextTabs)]);

        $clientUserId = (string)($user['id']); // pour signature embarquée

        $signerName = $forcedSignerName ?: $this->create_fullname($user['first_name'] ?? '', $user['last_name'] ?? '');

        $signer = new TemplateRole([
            'role_name'  => 'Client',
            'email'      => (string)$user['email'],
            'name'       => $signerName, // IMPORTANT pour éviter INVALID_USERNAME_FOR_RECIPIENT
            'tabs'       => $tabs,
        ]);

        if ($embedded) {
            $signer['client_user_id']            = $clientUserId;
            $signer['embeddedRecipientStartURL'] = 'SIGN_AT_DOCUSIGN';
        }

        $envelope = new EnvelopeDefinition([
            'status'         => 'sent',
            'template_id'    => $template_id,
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
        $connectUrl = (string) (config('services.docusign.connect_url') ?? '');
        if ($connectUrl) {
            $eventNotification = new EventNotification();
            $eventNotification->setUrl($connectUrl);
            $eventNotification->setRequireAcknowledgment('true');
            $eventNotification->setIncludeDocuments('true');
            $eventNotification->setLoggingEnabled('true');
            $eventNotification->setEnvelopeEvents([
                (new EnvelopeEvent())
                    ->setEnvelopeEventStatusCode('completed')
                    ->setIncludeDocuments('true')
            ]);
            $envelope->setEventNotification($eventNotification);
        }

        return $envelope;
    }

    /* -----------------------------------------------------------
     | API: Créer l’enveloppe de procuration
     * ----------------------------------------------------------*/
    public function requestSignature(Request $request)
    {
        // Auth API requise (admin/consultant)
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
            'agent_full_name'        => 'nullable|string',
            'agent_full_address'     => 'nullable|string',
            'agent_phone'            => 'nullable|string',
            'agent_email'            => 'nullable|email',
            'procuration_scope'      => 'nullable|string',
            'procuration_start_date' => 'nullable|string',
            'procuration_end_date'   => 'nullable|string',
            'embedded'               => 'sometimes|boolean',

            // Overrides facultatifs si ta BDD n'a pas les données
            'birth_date'             => 'sometimes|string',
            'nir_body'               => 'sometimes|string',
            'nir_key'                => 'sometimes|string',
            'address'                => 'sometimes|string',
            'address2'               => 'sometimes|string',
            'zip'                    => 'sometimes|string',
            'city'                   => 'sometimes|string',
            'country'                => 'sometimes|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->messages()], 422);
        }

        // Récup utilisateur
        $user = User::find($request->user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Normalise pour les anciens tabs "principal_*" (avec alias)
        $userData = [
            'id'        => $user->id,
            'email'     => $user->email,

            // essaie d'abord first_name/last_name, sinon dérive depuis "name"
            'first_name'=> $user->first_name
                ?? $user->firstname
                ?? (function($n){ $p = preg_split('/\s+/', trim((string)$n)); return $p[0] ?? ''; })($user->name ?? null),

            'last_name' => $user->last_name
                ?? $user->lastname
                ?? (function($n){
                        $n = trim((string)$n);
                        if ($n === '') return '';
                        $p = preg_split('/\s+/', $n);
                        array_shift($p);
                        return trim(implode(' ', $p));
                    })($user->name ?? null),

            'name'      => $user->name ?? null,

            // Adresse user avec alias
            'adr'       => $this->pick($user, ['personal_adr','address','adr','street','address1']),
            'zip'       => $this->pick($user, ['personal_zip','zip','zipcode','zip_code','postal_code']),
            'city'      => $this->pick($user, ['personal_city','city','ville']),
            'country'   => $this->pick($user, ['personal_country','country','pays']),
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
        $templateId = (string) config('services.docusign.template_procuration');
        if (!$templateId) {
            return response()->json(['error' => 'Missing DOCUSIGN_PROCURATION_TEMPLATE_ID (config services.docusign.template_procuration)'], 500);
        }

        // ----------- PRE-REMPLISSAGE depuis personal_informations -----------
        $piRow = DB::table('personal_informations')->where('user_id', $user->id)->first();

        $pi = [
            'first_name'       => $this->pick($piRow, ['first_name','firstname','prenom']) 
                                  ?? $this->pickInput($request, ['first_name','firstname','prenom'])
                                  ?? $this->pick($user, ['first_name','firstname']),
            'birth_last_name'  => $this->pick($piRow, ['maiden_name','madien_name','birth_last_name','nom_naissance'])
                                  ?? $this->pickInput($request, ['maiden_name','madien_name','birth_last_name','nom_naissance'])
                                  ?? $this->pick($user, ['last_name','lastname']),
            'usage_last_name'  => $this->pick($piRow, ['last_name','lastname','nom_usage'])
                                  ?? $this->pickInput($request, ['last_name','lastname','nom_usage'])
                                  ?? $this->pick($user, ['last_name','lastname']),
            'birth_date'       => $this->pick($piRow, ['birth_date','birthday','birthdate','date_of_birth','dob'])
                                  ?? $this->pickInput($request, ['birth_date','birthday','birthdate','date_of_birth','dob'])
                                  ?? $this->pick($user, ['birth_date','birthday','birthdate','date_of_birth','dob']),
            'nir_body'         => $this->pick($piRow, ['secu_social','securite_sociale','nir','num_secu','numero_secu','numero_securite_sociale'])
                                  ?? $this->pickInput($request, ['nir_body','nir','secu_social','num_secu','numero_secu','numero_securite_sociale']),
            'nir_key'          => $this->pick($piRow, ['secu_social_key','nir_key','cle','key','cle_secu'])
                                  ?? $this->pickInput($request, ['nir_key','cle','cle_secu','key']),
            'adr1'             => $this->pick($piRow, ['personal_address','address','adr','street','street1','addr1'], '')
                                  ?? $this->pickInput($request, ['personal_address','address','address1','adr','street','street1','addr1'], ''),
            'adr2'             => $this->pick($piRow, ['personal_address_2','address2','street2','addr2'], '')
                                  ?? $this->pickInput($request, ['personal_address_2','address2','street2','addr2'], ''),
            'zip'              => $this->pick($piRow, ['personal_zip_code','personal_zip','zip','zipcode','zip_code','postal_code'], '')
                                  ?? $this->pickInput($request, ['personal_zip_code','personal_zip','zip','zipcode','zip_code','postal_code'], ''),
            'city'             => $this->pick($piRow, ['personal_city','city','ville'], '')
                                  ?? $this->pickInput($request, ['personal_city','city','ville'], ''),
            'country'          => $this->pick($piRow, ['personal_country','country','pays'], '')
                                  ?? $this->pickInput($request, ['personal_country','country','pays'], ''),
        ];

        Log::debug('PI raw (after alias pick)', [
            'birth_date_raw' => $pi['birth_date'] ?? null,
            'nir_body_raw'   => $pi['nir_body'] ?? null,
            'nir_key_raw'    => $pi['nir_key'] ?? null,
            'adr1_raw'       => $pi['adr1'] ?? null,
            'adr2_raw'       => $pi['adr2'] ?? null,
            'zip_raw'        => $pi['zip'] ?? null,
            'city_raw'       => $pi['city'] ?? null,
            'country_raw'    => $pi['country'] ?? null,
        ]);

        $firstName = (string)($pi['first_name'] ?? $userData['first_name'] ?? '');
        $birthLN   = (string)($pi['birth_last_name'] ?? $userData['last_name'] ?? '');
        $usageLN   = (string)($pi['usage_last_name'] ?? $birthLN);

        $dobJJMMYYYY = $this->dobToJJMMYYYY($pi['birth_date'] ?? null);
        $dobChars    = $this->splitChars($dobJJMMYYYY, 8);

        $nirBody = (string)($pi['nir_body'] ?? '');
        $nirKey  = (string)($pi['nir_key']  ?? '');
        $nirFull = $this->onlyDigits($nirBody.$nirKey); // nettoie espaces/points/etc.
        $nirChars= $this->splitChars($nirFull, 15);

        // Concat adresse multilignes depuis PI
        $fullAddress = implode(' ', array_filter(array_map(function ($s) {
            return trim((string)$s);
        }, [
            $pi['adr1']    ?? '',
            $pi['adr2']    ?? '',
            $pi['zip']     ?? '',
            $pi['city']    ?? '',
            $pi['country'] ?? ''
        ]), fn($s) => $s !== ''));

        // Sécurité : supprime \r/\n éventuels et compresse les espaces
        $fullAddress = preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $fullAddress));

        // Fallback 1: adresse User (une ligne)
        if ($fullAddress === '') {
            $fullAddress = $this->create_full_address(
                $userData['adr'] ?? '',
                $userData['zip'] ?? '',
                $userData['city'] ?? '',
                $userData['country'] ?? ''
            );
        }

        // Fallback 2: adresse envoyée en payload
        if ($fullAddress === '') {
            $addr1 = $this->pickInput($request, ['address','address1','street','personal_address'], '');
            $addr2 = $this->pickInput($request, ['address2','street2','personal_address_2'], '');
            $zip   = $this->pickInput($request, ['zip','zipcode','zip_code','postal_code'], '');
            $city  = $this->pickInput($request, ['city','ville'], '');
            $ctry  = $this->pickInput($request, ['country','pays'], '');

            $fullAddress = implode(' ', array_filter(array_map(function ($s) {
                return trim((string)$s);
            }, [
                $addr1,
                $addr2,
                $zip,
                $city,
                $ctry
            ]), fn($s) => $s !== ''));

            $fullAddress = preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $fullAddress));
        }


        $extraTextTabs = [];
        $extraTextTabs[] = new Text(['tab_label' => 'birth_last_name', 'value' => $birthLN]);
        $extraTextTabs[] = new Text(['tab_label' => 'usage_last_name', 'value' => $usageLN]);
        $extraTextTabs[] = new Text(['tab_label' => 'first_name',      'value' => $firstName]);
        for ($i=1; $i<=8;  $i++)  $extraTextTabs[] = new Text(['tab_label' => "dob_$i", 'value' => $dobChars[$i-1] ?? '']);
        for ($i=1; $i<=15; $i++)  $extraTextTabs[] = new Text(['tab_label' => "nir_$i", 'value' => $nirChars[$i-1] ?? '']);
        $extraTextTabs[] = new Text(['tab_label' => 'full_address',    'value' => $fullAddress]);

        $forcedSignerName = $this->signerNameFromPI(
            ['first_name'=>$firstName, 'usage_last_name'=>$usageLN],
            $userData
        );
        // --------------------------------------------------------------------

        try {
            $api       = $this->dsClient();
            $accountId = (string) config('services.docusign.account_id');
            Log::debug('DS prefill', [
                'dobJJMMYYYY' => $dobJJMMYYYY,
                'dobChars'    => $dobChars,
                'nirFull'     => $nirFull,
                'nirLen'      => strlen($nirFull),
                'firstName'   => $firstName,
                'usageLN'     => $usageLN,
                'fullAddress' => $fullAddress,
            ]);

            $env   = $this->make_procuration_envelope($userData, $procu, $templateId, $embedded, $extraTextTabs, $forcedSignerName);
            $res   = $api->createEnvelope($accountId, $env);
            $envId = $res->getEnvelopeId();

            if (!$envId) {
                return response()->json(['error' => 'Unable to create envelope'], 400);
            }

            // Lien de signature embarquée (si demandé)
            $signingUrl = null;
            if ($embedded) {
                try {
                    $viewReq = new RecipientViewRequest([
                        'authentication_method' => 'none',
                        'client_user_id'        => (string)$userData['id'],
                        'email'                 => $userData['email'],
                        'user_name'             => $forcedSignerName,
                        'return_url'            => rtrim(config('app.url'), '/') . '/docusign-return?envelopeId=' . $envId,
                    ]);
                    $viewRes   = $api->createRecipientView($accountId, $envId, $viewReq);
                    $signingUrl = $viewRes->getUrl();
                } catch (DSEApiException $e) {
                    Log::warning('createRecipientView failed: '.$e->getMessage());
                }
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
            $accountId = (string) config('services.docusign.account_id');
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
                'user_name'             => $this->fallbackSignerName([
                    'first_name' => $user->first_name ?? $user->firstname ?? null,
                    'last_name'  => $user->last_name  ?? $user->lastname  ?? null,
                    'name'       => $user->name ?? null,
                    'email'      => $user->email,
                ]),
                'return_url'            => $request->get('return_url', rtrim(config('app.url'), '/') . '/docusign-return?envelopeId=' . $envelopeId),
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
        $connectKey = (string) (config('services.docusign.connect_key') ?? '');
        if ($connectKey) {
            $sigHeader = $request->header('X-DocuSign-Signature-1');
            $rawBody   = $request->getContent();
            $calc      = base64_encode(hash_hmac('sha256', $rawBody, $connectKey, true));
            if (!$sigHeader || !hash_equals($calc, $sigHeader)) {
                Log::warning('Invalid DocuSign HMAC');
                return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
            }
        }

        // 1) Payload
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

        $customFields = $summary['customFields']['textCustomFields'] ?? [];
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
// 3) Stockage
try {
    $file   = base64_decode($pdfBytes);
    $userId = (string)$user->id;
    $time   = $this->safeFileSuffix($summary['completedDateTime'] ?? now()->toIso8601String());

    if ($docType === 'procuration') {
        Storage::disk('users')->makeDirectory("$userId/contract/procuration");
        $storedPath = "$userId/contract/procuration/procuration-$time.pdf";

        if (!Storage::disk('users')->exists($storedPath)) {
            Storage::disk('users')->put($storedPath, $file);
        }

        $exists = Documents::where([
            'user_id'     => $userId,
            'type'        => 'procuration',
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

        return response()->json(['success'=>true], 200); // <-- AJOUTE UN RETURN ICI
        } elseif ($docType === 'contract') {                 // <-- ICI: elseif au lieu d’un 2e if
            Storage::disk('users')->makeDirectory("$userId/contract/signed");
            $storedPath = "$userId/contract/signed/contract-$time.pdf";

            if (!Storage::disk('users')->exists($storedPath)) {
                Storage::disk('users')->put($storedPath, $file);
            }

            $exists = Documents::where([
                'user_id'     => $userId,
                'type'        => 'contract',
                'link_to_doc' => $storedPath,
            ])->exists();

            if (!$exists) {
                Documents::create([
                    'user_id'     => $userId,
                    'link_to_doc' => $storedPath,
                    'title'       => basename($storedPath),
                    'type'        => 'contract',
                    'is_approuved'=> true,
                ]);
            }

            return response()->json(['success'=>true], 200);
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
}
