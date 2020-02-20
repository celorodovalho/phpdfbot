<?php

return [
    'session_path' => storage_path('app/session.madeline'),
    'app_info' => [
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
    ],
    'logger' => [
        'logger' => \danog\MadelineProto\Logger::FILE_LOGGER,
        'logger_level' => \danog\MadelineProto\Logger::VERBOSE,
        'logger_param' => storage_path('logs/laravel.log'),
        'param' => storage_path('logs/laravel.log'),
    ]
];
