<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContractDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One physical device unit on a contract, identified by its serial number.
 */
#[Table(name: 'contract_device', incrementing: true)]
#[Fillable(['contract_id', 'device_id', 'serial_number'])]
class ContractDevice extends Pivot
{
    /** @use HasFactory<ContractDeviceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
