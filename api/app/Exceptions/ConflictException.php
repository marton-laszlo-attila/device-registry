<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

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

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
