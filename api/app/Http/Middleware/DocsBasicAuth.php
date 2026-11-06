<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Protects the API documentation with HTTP Basic Auth.
 *
 * Fails closed: without configured credentials nobody gets in.
 */
final class DocsBasicAuth
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hasValidCredentials($request)) {
            throw new UnauthorizedHttpException('Basic realm="API Docs"', 'Invalid credentials.');
        }

        return $next($request);
    }

    private function hasValidCredentials(Request $request): bool
    {
        $username = (string) config('docs.username');
        $password = (string) config('docs.password');

        if ($username === '' || $password === '') {
            return false;
        }

        // Both are compared in constant time, and always both, so the timing reveals nothing.
        $validUsername = hash_equals($username, (string) $request->getUser());
        $validPassword = hash_equals($password, (string) $request->getPassword());

        return $validUsername && $validPassword;
    }
}
