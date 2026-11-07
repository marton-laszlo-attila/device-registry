<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DeviceCategory;
use App\Models\Device;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * The rules here are the single source for both create and update
 * (UpdateDeviceRequest makes every field optional, and checks the pair from the
 * manufacturer side when only the manufacturer is sent).
 */
class StoreDeviceRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Enum|Unique|string>>
     */
    public function rules(): array
    {
        return [
            'manufacturer' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100', $this->uniqueDeviceType('model')],
            'category' => ['required', Rule::enum(DeviceCategory::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The manufacturer and model pair must be unique. The counterpart value falls
     * back to the stored device, so partial updates are checked as well.
     *
     * The value goes in through a query callback: a plain where() would be
     * serialized into the rule string, where a quote and a backslash in the
     * input could add parameters to the rule (and end in a 500).
     */
    protected function uniqueDeviceType(string $attribute): Unique
    {
        $device = $this->route('device');
        $device = $device instanceof Device ? $device : null;
        $counterpart = $attribute === 'model' ? 'manufacturer' : 'model';
        $counterpartValue = $this->input($counterpart, $device?->getAttribute($counterpart));

        return Rule::unique('devices', $attribute)
            ->where(fn (Builder $query): Builder => $query->where($counterpart, $counterpartValue))
            ->ignore($device);
    }
}
