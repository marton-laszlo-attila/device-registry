<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ConflictException extends ApiException
{
    public static function deviceInUse(): self
    {
        return new self('The device cannot be deleted while it is assigned to a contract.');
    }

    public static function contractHasDevices(): self
    {
        return new self('The contract cannot be deleted while it has devices assigned.');
    }

    /**
     * A unique constraint of the database was violated, typically by two
     * concurrent requests that both passed validation.
     */
    public static function duplicateRecord(?Throwable $previous = null): self
    {
        return new self('The resource conflicts with an existing record.', previous: $previous);
    }

    /**
     * A foreign key of the database was violated: a related record was deleted
     * or started to be used by a concurrent request after the checks passed.
     */
    public static function concurrentChange(?Throwable $previous = null): self
    {
        return new self('A related record was changed by a concurrent request.', previous: $previous);
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
