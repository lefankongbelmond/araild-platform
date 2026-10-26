<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => (bool) env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        // Default stack: structured JSON to a daily file, plus stderr in containers.
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'structured')),
            'ignore_exceptions' => false,
        ],

        // JSON lines — correlatable via the request_id/tenant context added by
        // the RequestContext middleware. Ideal for shipping to a log aggregator.
        'structured' => [
            'driver' => 'daily',
            'path' => storage_path('logs/mxconnect.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => (int) env('LOG_DAILY_DAYS', 14),
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'replace_placeholders' => true,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'with' => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],

        'emergency' => ['path' => storage_path('logs/laravel.log')],
    ],
];
