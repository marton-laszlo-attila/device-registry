<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Contract;
use App\Services\Interfaces\ContractServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ContractService implements ContractServiceInterface
{
    public function list(int $perPage): LengthAwarePaginator
    {
        return Contract::query()->orderBy('id')->paginate($perPage);
    }

    public function create(array $data): Contract
    {
        return Contract::query()->create($data);
    }

    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);

        return $contract;
    }

    public function delete(Contract $contract): void
    {
        if ($contract->assignments()->exists()) {
            throw ConflictException::contractHasDevices();
        }

        $contract->delete();
    }
}
