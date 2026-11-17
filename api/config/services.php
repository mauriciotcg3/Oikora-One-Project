<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
        // Códigos de identidade (T-11): token do servidor e remetente verificado.
        'token' => env('POSTMARK_SERVER_TOKEN'),
        'remetente' => env('POSTMARK_REMETENTE'),
        'stream' => env('POSTMARK_STREAM', 'outbound'),
    ],

    // Número da plataforma, só para códigos de identidade (OIK-ADR-0008 §10).
    'meta_whatsapp' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'numero_id' => env('META_WHATSAPP_NUMERO_ID'),
        'versao' => env('META_GRAPH_VERSAO', 'v23.0'),
        'idioma' => env('META_WHATSAPP_IDIOMA', 'pt_BR'),
        'template_codigo' => env('META_WHATSAPP_TEMPLATE_CODIGO'),
    ],

    // SMS de reserva de código, só +55 (OIK-ADR-0008 §11): serviço de mensagens ou número remetente.
    'twilio' => [
        'conta_sid' => env('TWILIO_CONTA_SID'),
        'token' => env('TWILIO_TOKEN'),
        'servico_sid' => env('TWILIO_SERVICO_SID'),
        'remetente' => env('TWILIO_REMETENTE'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
