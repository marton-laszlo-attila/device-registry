<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceCategory;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A device type (manufacturer + model). Physical units are the assignments.
 */
#[Fillable(['manufacturer', 'model', 'category', 'description'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Contracts this device type appears on, with the serial number of each unit.
     *
     * @return BelongsToMany<Contract, $this, ContractDevice>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class)
            ->using(ContractDevice::class)
            ->withPivot('id', 'serial_number')
            ->withTimestamps();
    }

    /**
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
            'category' => DeviceCategory::class,
        ];
    }
}
