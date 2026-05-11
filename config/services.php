<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'n8n' => [
        'consultant_access_url' => env('N8N_CONSULTANT_ACCESS_URL'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 120),
    ],

    'docusign' => [
    'base_path'   => env('DOCUSIGN_BASE_PATH', 'account.docusign.com'),
    'api_path'    => env('DOCUSIGN_API_PATH', 'https://eu.docusign.net/restapi'),
    'account_id'  => env('DOCUSIGN_ACCOUNT_ID'),
    'client_id'   => env('DOCUSIGN_CLIENT_ID'),
    'user_id'     => env('DOCUSIGN_USER_ID'),
    'private_key' => env('DOCUSIGN_KEY_PRIVATE'),
    'template_procuration' => env('DOCUSIGN_PROCURATION_TEMPLATE_ID'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
