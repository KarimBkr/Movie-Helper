<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    // Analyse IA (CLAUDE.md règle 5) : file dédiée, max 2 workers en prod,
    // 2 tentatives, backoff 30s. Le nombre de workers est appliqué au lancement
    // de queue:work, pas ici.
    'ai' => [
        'queue' => env('AI_QUEUE_NAME', 'ai'),
        'tries' => (int) env('AI_JOB_TRIES', 2),
        'backoff' => (int) env('AI_JOB_BACKOFF_SECONDS', 30),
        'max_workers' => (int) env('AI_QUEUE_MAX_WORKERS', 2),
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],
];
