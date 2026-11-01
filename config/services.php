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

    'efactura' => [
        'java_binary' => env('EFACTURA_JAVA_BINARY', 'java'),
        'oauth_token_url' => env('EFACTURA_OAUTH_TOKEN_URL', 'https://logincert.anaf.ro/anaf-oauth2/v1/token'),
        'timeout_seconds' => (int) env('EFACTURA_TIMEOUT_SECONDS', 30),
        'connect_timeout_seconds' => (int) env('EFACTURA_CONNECT_TIMEOUT_SECONDS', 5),
        'environments' => [
            'test' => [
                'upload_url' => env('EFACTURA_TEST_UPLOAD_URL', 'https://api.anaf.ro/test/FCTEL/rest/upload'),
                'status_url' => env('EFACTURA_TEST_STATUS_URL', 'https://api.anaf.ro/test/FCTEL/rest/stareMesaj'),
                'download_url' => env('EFACTURA_TEST_DOWNLOAD_URL', 'https://api.anaf.ro/test/FCTEL/rest/descarcare'),
                'client_id' => env('EFACTURA_TEST_CLIENT_ID'),
                'client_secret' => env('EFACTURA_TEST_CLIENT_SECRET'),
                'refresh_token' => env('EFACTURA_TEST_REFRESH_TOKEN'),
            ],
            'production' => [
                'upload_url' => env('EFACTURA_PRODUCTION_UPLOAD_URL', 'https://api.anaf.ro/prod/FCTEL/rest/upload'),
                'status_url' => env('EFACTURA_PRODUCTION_STATUS_URL', 'https://api.anaf.ro/prod/FCTEL/rest/stareMesaj'),
                'download_url' => env('EFACTURA_PRODUCTION_DOWNLOAD_URL', 'https://api.anaf.ro/prod/FCTEL/rest/descarcare'),
                'client_id' => env('EFACTURA_PRODUCTION_CLIENT_ID'),
                'client_secret' => env('EFACTURA_PRODUCTION_CLIENT_SECRET'),
                'refresh_token' => env('EFACTURA_PRODUCTION_REFRESH_TOKEN'),
            ],
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'market_intelligence' => [
        'max_attempts' => (int) env('MARKET_INTELLIGENCE_MAX_ATTEMPTS', 3),
        'retry_delay_ms' => (int) env('MARKET_INTELLIGENCE_RETRY_DELAY_MS', 100),
    ],

    'google_ads' => [
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'api_version' => 'v26',
        'timeout' => (int) env('GOOGLE_ADS_TIMEOUT', 30),
        'max_range_days' => (int) env('GOOGLE_ADS_MAX_RANGE_DAYS', 90),
    ],
];
