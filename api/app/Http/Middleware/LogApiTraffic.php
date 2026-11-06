<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stores every API request and its response in the api_logs table.
 *
 * Registered as global middleware, so requests that match no route
 * (404, 405) are logged as well. The row is written in terminate(),
 * after the response has been sent to the client.
 *
 * Every stored string is made valid UTF-8 first: a single invalid byte
 * would otherwise make the insert fail, and the request would leave no trace.
 */
final class LogApiTraffic
{
    private const string MASK = '***';

    private const string TRUNCATION_MARKER = '... [truncated]';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('api_logging.enabled') || ! $request->is('api/*')) {
            return;
        }

        try {
            ApiLog::query()->create([
                'method' => $request->method(),
                'url' => $this->utf8($request->fullUrl()),
                'status_code' => $response->getStatusCode(),
                'request_headers' => $this->headers($request),
                'request_body' => $this->body($request->getContent()),
                'response_body' => $this->body($response->getContent()),
                'ip' => $request->ip(),
                'duration_ms' => $this->durationMs($request),
            ]);
        } catch (Throwable $e) {
            // Logging must never affect the API response.
            report($e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $masked = config('api_logging.masked_headers');

        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array($name, $masked, true) ? self::MASK : $this->utf8(implode(', ', $values));
        }

        return $headers;
    }

    private function body(string|false $content): ?string
    {
        if ($content === false || $content === '') {
            return null;
        }

        $content = $this->utf8($content);
        $maxBytes = (int) config('api_logging.max_body_bytes');

        // mb_strcut() counts bytes, as the storage does, but never splits a character.
        return strlen($content) > $maxBytes
            ? mb_strcut($content, 0, $maxBytes) . self::TRUNCATION_MARKER
            : $content;
    }

    /**
     * Replaces invalid UTF-8 byte sequences with "?".
     */
    private function utf8(string $value): string
    {
        return mb_scrub($value, 'UTF-8');
    }

    private function durationMs(Request $request): int
    {
        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
