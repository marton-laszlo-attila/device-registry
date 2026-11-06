<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Documentation Access
    |--------------------------------------------------------------------------
    |
    | Basic Auth credentials of the OpenAPI documentation (/docs/api and
    | /docs/api.json). If either is empty, every request is denied.
    |
    */

    'username' => env('DOCS_USERNAME'),

    'password' => env('DOCS_PASSWORD'),

];
