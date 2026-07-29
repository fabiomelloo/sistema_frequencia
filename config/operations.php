<?php

return [
    'authentication' => [
        'max_failed_attempts' => (int) env('AUTH_MAX_FAILED_ATTEMPTS', 5),
        'lockout_minutes' => (int) env('AUTH_LOCKOUT_MINUTES', 15),
    ],
    'legacy' => [
        // Preservado somente para rastreabilidade historica. O fluxo operacional e nativo.
        'spreadsheet_import_enabled' => false,
    ],
    'readiness' => [
        'cache_key' => env('READINESS_CACHE_KEY', 'system:readiness'),
        'storage_path' => env('READINESS_STORAGE_PATH', 'health/readiness.txt'),
    ],
    'backup' => [
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'interval_seconds' => (int) env('BACKUP_INTERVAL_SECONDS', 86400),
        'encryption_key_configured' => strlen((string) env('BACKUP_ENCRYPTION_KEY', '')) >= 32,
    ],
];
