<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Traffic Logging
    |--------------------------------------------------------------------------
    |
    | Every request under /api and its response is stored in the api_logs
    | table by the LogApiTraffic middleware.
    |
    */

    'enabled' => (bool) env('API_LOGGING_ENABLED', true),

    /*
     * Header names (lowercase) whose values are replaced by "***". Credentials
     * of any kind, including the php-auth-* values that PHP derives from a
     * Basic or Digest Authorization header.
     */
    'masked_headers' => [
        'authorization',
        'proxy-authorization',
        'cookie',
        'php-auth-user',
        'php-auth-pw',
        'php-auth-digest',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
    ],

    /*
     * Request and response bodies longer than this (in bytes) are truncated.
     */
    'max_body_bytes' => 65535,

    /*
     * Log rows older than this many days are deleted by the scheduled
     * model:prune command (see routes/console.php).
     */
    'retention_days' => (int) env('API_LOG_RETENTION_DAYS', 30),

];
