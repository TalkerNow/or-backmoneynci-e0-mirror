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
