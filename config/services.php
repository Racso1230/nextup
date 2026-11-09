<?php

// Parsed once for the Steam OpenID settings below.
$appUrl = parse_url((string) env('APP_URL', 'http://localhost')) + ['host' => 'localhost'];

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
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'steam' => [
        // Our own SteamClient reads `key`; the Socialite provider reads the rest.
        'key' => env('STEAM_API_KEY'),

        // SocialiteProviders/Steam settings. The provider has no OAuth client id,
        // and it uses `client_secret` as the Web API key for its profile lookup.
        'client_id' => null,
        'client_secret' => env('STEAM_API_KEY'),
        'redirect' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/steam/callback',

        // OpenID realm (host[:port]) and the only host a return_to may use. Both come from APP_URL.
        'realm' => $appUrl['host'].(isset($appUrl['port']) ? ':'.$appUrl['port'] : ''),
        'allowed_hosts' => [$appUrl['host']],
        'force_https' => ($appUrl['scheme'] ?? 'http') === 'https',
    ],

    'igdb' => [
        'client_id' => env('IGDB_CLIENT_ID'),
        'client_secret' => env('IGDB_CLIENT_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
