<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
    </head>
    <body>
        <h1>{{ config('app.name') }}</h1>
        <p>REST API. See <code>/api/v1</code>; documentation is available at <code>/docs/api</code>.</p>
    </body>
</html>
