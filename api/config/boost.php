<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | Off: the project has no frontend, and the watcher's POST /_boost/browser-logs
    | route takes any text without authentication or CSRF check, writes it to
    | storage/logs/browser.log and hands it to the coding agent via the Boost MCP
    | server. Only the keys set here override the package defaults.
    |
    */

    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', false),

];
