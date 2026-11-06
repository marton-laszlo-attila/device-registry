<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContractDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A device unit on a contract: the assignment id (used to remove it) and its serial number.
 *
 * @mixin ContractDevice
 */
class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial_number' => $this->serial_number,
            'assigned_at' => $this->created_at,
            'device' => DeviceResource::make($this->whenLoaded('device')),
        ];
    }
}
