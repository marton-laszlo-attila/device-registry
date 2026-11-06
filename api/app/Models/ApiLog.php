<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One request/response pair of the API traffic. Written once, never updated,
 * deleted by the scheduled model:prune command after the retention period.
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
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * The rows older than the retention period of config/api_logging.php.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('api_logging.retention_days')));
    }

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
