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
                'url' => $request->fullUrl(),
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
            $headers[$name] = in_array($name, $masked, true) ? self::MASK : implode(', ', $values);
        }

        return $headers;
    }

    private function body(string|false $content): ?string
    {
        if ($content === false || $content === '') {
            return null;
        }

        $maxLength = (int) config('api_logging.max_body_length');

        return mb_strlen($content) > $maxLength
            ? mb_substr($content, 0, $maxLength) . self::TRUNCATION_MARKER
            : $content;
    }

    private function durationMs(Request $request): int
    {
        $startedAt = (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
