<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['contract_number', 'partner_name', 'start_date', 'end_date', 'status', 'notes'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * Device types on the contract, with the serial number of each unit.
     *
     * @return BelongsToMany<Device, $this, ContractDevice>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)
            ->using(ContractDevice::class)
            ->withPivot('id', 'serial_number')
            ->withTimestamps();
    }

    /**
     * Assignment rows; also used by the scoped route binding.
     *
     * @return HasMany<ContractDevice, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ContractDevice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ContractStatus::class,
        ];
    }
}
