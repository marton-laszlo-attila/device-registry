<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One request/response pair of the API traffic. Written once, never updated.
 */
#[Fillable([
    'method',
    'url',
    'status_code',
    'request_headers',
    'request_body',
    'response_body',
    'ip',
    'duration_ms',
])]
class ApiLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
