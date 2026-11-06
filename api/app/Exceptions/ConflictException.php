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

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
