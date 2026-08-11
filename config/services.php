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

    'psychotest' => [
        'base_url' => env('PSYCHOTEST_API_URL'),
        'token' => env('PSYCHOTEST_API_TOKEN'),
        'test_ids' => array_values(array_filter(array_map(
            fn (string $id): string => trim($id),
            explode(',', (string) env('PSYCHOTEST_TEST_IDS', '')),
        ))),
    ],

    'platonus' => [
        'verify_url' => env('PLATONUS_VERIFY_URL', 'https://hub.atu.kz/api/v1/students/verify'),
        'student_full_url' => env('PLATONUS_STUDENT_FULL_URL', 'https://hub.atu.kz/api/v1/hub/student_full'),
        'api_key' => env('PLATONUS_API_KEY'),
        'timeout' => env('PLATONUS_TIMEOUT', 15),
    ],

];
