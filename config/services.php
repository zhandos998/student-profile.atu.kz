<?php

return [

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

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

    'platonus' => [
        'verify_url' => env('PLATONUS_VERIFY_URL', 'https://hub.atu.kz/api/v1/students/verify'),
        'tutor_verify_url' => env('PLATONUS_TUTOR_VERIFY_URL', 'https://hub.atu.kz/api/v1/tutors/verify'),
        'student_full_url' => env('PLATONUS_STUDENT_FULL_URL', 'https://hub.atu.kz/api/v1/hub/student_full'),
        'surveys_url' => env('PLATONUS_SURVEYS_URL', 'https://hub.atu.kz/api/v1/students/surveys'),
        'api_key' => env('PLATONUS_API_KEY'),
        'timeout' => env('PLATONUS_TIMEOUT', 15),
    ],

    'library_barcode' => [
        'url' => env('LIBRARY_BARCODE_URL', 'https://library.atu.edu.kz/setBarcode.asp'),
        'timeout' => env('LIBRARY_BARCODE_TIMEOUT', 30),
        'bookpoints' => env('LIBRARY_BARCODE_BOOKPOINTS', 'аб,чз/1,чз/2,сбо,АТУ(ТЭК),АТУ аб.корпус/3б,АТУ чз корпус/3б,Центр электронных ресурсов 1,Центр электронных ресурсов 2,НБ АТУ,Абонемент 1,Абонемент 2'),
        'statuses' => [
            'bachelor' => 'Бакалавр',
            'master' => env('LIBRARY_BARCODE_STATUS_MASTER', 'Магистратура'),
            'doctorate' => env('LIBRARY_BARCODE_STATUS_DOCTORATE', 'Докторантура'),
        ],
    ],

];
