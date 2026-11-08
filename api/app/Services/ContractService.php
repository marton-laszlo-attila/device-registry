<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Services\Interfaces\ContractServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class ContractService implements ContractServiceInterface
{
    public function list(int $perPage, int $page): LengthAwarePaginator
    {
        // The page size goes into the pagination links, otherwise following one falls back to the default size.
        return Contract::query()->orderBy('id')->paginate($perPage, page: $page)->appends(['per_page' => $perPage]);
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

    public function assignDevice(Contract $contract, array $data): ContractDevice
    {
        return $contract->assignments()->create($data)->load('device');
    }

    public function removeAssignment(ContractDevice $assignment): void
    {
        $assignment->delete();
    }
}
