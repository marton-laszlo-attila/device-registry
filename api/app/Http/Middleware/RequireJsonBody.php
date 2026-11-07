<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Write requests of the API must send a JSON body (415 otherwise).
 *
 * The API has no authentication, so any page open in a browser that can reach
 * it could change data. A cross-site HTML form can send form-encoded or text/plain
 * bodies without a CORS preflight, and `_method` turns such a POST into a PUT or
 * DELETE. A JSON body always needs a preflight, which the CORS policy refuses for
 * foreign origins. The real method is checked, so `_method` cannot get around it.
 *
 * The media type itself is compared, not the whole header: Laravel's isJson() only
 * looks for "/json" anywhere in it, so "text/plain; x=/json" (no preflight) would pass.
 */
final class RequireJsonBody
{
    private const array WRITE_METHODS = ['POST', 'PUT', 'PATCH'];

    private const string MESSAGE = 'The request body must be JSON (Content-Type: application/json).';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getRealMethod(), self::WRITE_METHODS, true) && ! $this->hasJsonBody($request)) {
            throw new UnsupportedMediaTypeHttpException(self::MESSAGE);
        }

        return $next($request);
    }

    /**
     * application/json or a "+json" type, parameters such as charset ignored.
     */
    private function hasJsonBody(Request $request): bool
    {
        $contentType = (string) $request->headers->get('Content-Type');
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $mediaType === 'application/json' || str_ends_with($mediaType, '+json');
    }
}
