<?php

return [
    'session_path' => 'session.madeline',
    'app_info' => [
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
    ],
    'logger' => [
        'logger' => \danog\MadelineProto\Logger::DEFAULT_LOGGER,
        'logger_level' => \danog\MadelineProto\Logger::VERBOSE,
        'param' => storage_path('logs/laravel.log')
    ]
];
