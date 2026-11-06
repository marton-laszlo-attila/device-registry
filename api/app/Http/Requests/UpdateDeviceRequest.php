<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * PUT and PATCH are both partial: only the sent fields are validated and updated.
 */
class UpdateDeviceRequest extends StoreDeviceRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = array_map(fn (array $fieldRules): array => ['sometimes', ...$fieldRules], parent::rules());

        if ($this->missing('model')) {
            $rules['manufacturer'][] = $this->uniqueDeviceType('manufacturer');
        }

        return $rules;
    }
}
