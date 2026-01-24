<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Enable/Disable
    |--------------------------------------------------------------------------
    |
    | Master switch for the API debugger. When disabled, middleware does nothing.
    |
    */
    'enabled' => env('API_DEBUGGER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Logging Driver
    |--------------------------------------------------------------------------
    |
    | How to store logs: 'sync' writes directly to DB (simpler),
    | 'queue' dispatches a job (better performance under load).
    |
    */
    'driver' => env('API_DEBUGGER_DRIVER', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration (when driver = 'queue')
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('API_DEBUGGER_QUEUE_CONNECTION', 'redis'),
        'name' => env('API_DEBUGGER_QUEUE_NAME', 'api-debugger'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | Optionally use a separate database connection for logs.
    | Set to null to use the default connection.
    |
    */
    'connection' => env('API_DEBUGGER_DB_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Debug Session Settings
    |--------------------------------------------------------------------------
    */
    'session' => [
        // Default duration when enabling debug (minutes)
        'default_duration' => env('API_DEBUGGER_DEFAULT_DURATION', 30),

        // Maximum allowed duration (minutes)
        'max_duration' => env('API_DEBUGGER_MAX_DURATION', 120),

        // Cache TTL for session lookups (seconds)
        'cache_ttl' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Retention
    |--------------------------------------------------------------------------
    */
    'retention' => [
        // Auto-delete logs older than this (hours)
        'hours' => env('API_DEBUGGER_RETENTION_HOURS', 24),

        // Run cleanup every N minutes via scheduler
        'cleanup_interval' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request/Response Body Storage
    |--------------------------------------------------------------------------
    */
    'body' => [
        // Store request/response bodies (disable for metadata-only logging)
        'store' => true,

        // Maximum body size to store (bytes). Larger bodies are truncated with notice.
        // Set to null for unlimited (not recommended for production)
        'max_size' => env('API_DEBUGGER_MAX_BODY_SIZE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Filtering
    |--------------------------------------------------------------------------
    */
    'routes' => [
        // Only log routes matching these patterns (empty = log all)
        'include' => [
            // 'api/*',
        ],

        // Never log routes matching these patterns
        'exclude' => [
            'api-debugger/*',
            'telescope/*',
            'horizon/*',
            '_debugbar/*',
            'sanctum/*',
        ],

        // API route prefixes for the routes list view (supports wildcards)
        'api_prefixes' => [
            'api',
            'api/*',
            'tenant/*/api',
            '*/api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Redaction
    |--------------------------------------------------------------------------
    */
    'redact' => [
        // Headers to redact (case-insensitive)
        'headers' => [
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],

        // Request/response body fields to redact
        'fields' => [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'secret',
            'token',
            'api_key',
            'api_secret',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ],

        // Replacement text for redacted values
        'replacement' => '[REDACTED]',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Detection
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        // Enable tenant-aware debugging
        'enabled' => true,

        // Custom detector class (null = use built-in detector)
        'detector' => null,

        // Header name for tenant ID (if using header-based tenancy)
        'header' => 'X-Tenant-ID',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        // Auto-refresh log viewer
        'auto_refresh' => true,

        // Refresh interval in seconds
        'refresh_interval' => 5,

        // Logs per page
        'per_page' => 25,

        // Route prefix for UI
        'prefix' => 'api-debugger',

        // Middleware for UI routes
        'middleware' => ['web', 'auth'],

        // Date format for display
        'date_format' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Control who can access the debugger UI and manage sessions.
    |
    */
    'authorization' => [
        // Gate name to check (null = no gate check, rely on middleware only)
        'gate' => null,

        // Or specify allowed user IDs directly
        'allowed_users' => [],

        // Or specify allowed emails
        'allowed_emails' => [],
    ],
];
