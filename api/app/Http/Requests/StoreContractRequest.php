<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

/**
 * The rules here are the single source for both create and update
 * (UpdateContractRequest only makes every field optional).
 */
class StoreContractRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|Enum|Unique|string>>
     */
    public function rules(): array
    {
        $contract = $this->contract();

        return [
            'contract_number' => ['required', 'string', 'max:50', Rule::unique('contracts')->ignore($contract)],
            'partner_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date', ...$this->startDateLimit($contract)],
            'end_date' => ['nullable', 'date', 'after_or_equal:' . $this->startDateReference($contract)],
            'status' => ['required', Rule::enum(ContractStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * The contract being updated, or null when creating.
     */
    private function contract(): ?Contract
    {
        $contract = $this->route('contract');

        return $contract instanceof Contract ? $contract : null;
    }

    /**
     * The end date is compared to the sent start date, or to the stored one on a partial update.
     */
    private function startDateReference(?Contract $contract): string
    {
        if ($this->missing('start_date') && $contract !== null) {
            return $contract->start_date->toDateString();
        }

        return 'start_date';
    }

    /**
     * A partial update that only moves the start date must not pass the stored end date.
     *
     * @return array<int, string>
     */
    private function startDateLimit(?Contract $contract): array
    {
        if ($this->missing('end_date') && $contract?->end_date !== null) {
            return ['before_or_equal:' . $contract->end_date->toDateString()];
        }

        return [];
    }
}
