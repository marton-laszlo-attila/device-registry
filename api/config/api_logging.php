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
     * Header names (lowercase) whose values are replaced by "***".
     */
    'masked_headers' => [
        'authorization',
        'cookie',
        'php-auth-pw',
    ],

    /*
     * Request and response bodies longer than this (in characters) are truncated.
     */
    'max_body_length' => 65535,

];
