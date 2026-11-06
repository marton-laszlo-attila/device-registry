<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeviceRequest extends FormRequest
{
    /**
     * The serial number is unique across all contracts: one physical unit
     * can only be on one contract at a time.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'serial_number' => ['required', 'string', 'max:100', 'unique:contract_device,serial_number'],
        ];
    }
}
