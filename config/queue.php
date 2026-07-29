<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('QUEUE_DB_CONNECTION'),
            'table' => env('QUEUE_DB_TABLE', 'jobs'),
            'queue' => env('QUEUE_NAME', 'default'),
            'retry_after' => (int) env('QUEUE_RETRY_AFTER', 150),
            'after_commit' => true,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('QUEUE_BEANSTALKD_HOST', 'localhost'),
            'queue' => env('QUEUE_BEANSTALKD_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('QUEUE_REDIS_CONNECTION', 'default'),
            'queue' => env('QUEUE_REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
        'database' => env('QUEUE_FAILED_DATABASE', 'mysql'),
        'table' => env('QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],
];
