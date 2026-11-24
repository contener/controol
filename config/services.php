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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Service indépendant whatsapp-connector/ (Node.js) — gère les connexions WhatsApp
    // (QR) des agents IA. Reste vide tant qu'aucun hébergement adapté (processus Node
    // persistant) n'est en place : WhatsappConnectorClient::configure() pilote alors
    // l'affichage d'un simple placeholder côté interface, sans rien casser.
    'whatsapp_connector' => [
        'url' => env('WHATSAPP_CONNECTOR_URL'),
        'secret' => env('WHATSAPP_CONNECTOR_SECRET'),
    ],

    // Paiement en ligne des abonnements (CamerPay : Orange Money, MTN MoMo, carte,
    // PayPal), 2026-11-24. Clés UNIQUEMENT ici, côté serveur : jamais dans
    // l'application mobile (une clé embarquée dans l'APK se récupère en le
    // décompilant). api_key : jeton Bearer des appels serveur→CamerPay ;
    // webhook_secret : secret HMAC-SHA256 des notifications CamerPay→serveur.
    // Paiement en ligne désactivé tant que les deux ne sont pas renseignés.
    'camerpay' => [
        'base_url' => env('CAMERPAY_BASE_URL', 'https://camerpay.biz/api'),
        'api_key' => env('CAMERPAY_API_KEY'),
        'webhook_secret' => env('CAMERPAY_WEBHOOK_SECRET'),
    ],

];
