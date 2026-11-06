<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule violation that maps to a specific HTTP status code.
 */
abstract class ApiException extends RuntimeException
{
    abstract public function status(): int;
}
