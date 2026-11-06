<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeviceRequest extends FormRequest
{
    /**
     * Letters and digits, then also ".", "_", "/" or "-". ASCII only, so a look-alike
     * character (e.g. a Cyrillic "А") cannot register the same unit a second time.
     */
    private const string SERIAL_NUMBER_FORMAT = '/^[A-Za-z0-9][A-Za-z0-9._\/-]*\z/';

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
            'serial_number' => [
                'required',
                'string',
                'max:100',
                'regex:' . self::SERIAL_NUMBER_FORMAT,
                'unique:contract_device,serial_number',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial_number.regex' => 'The serial number may only contain letters, digits, ".", "_", "/" and "-", '
                . 'and must start with a letter or digit.',
        ];
    }
}
