<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\Exceptions\ConflictException;
use App\Models\Contract;
use App\Models\ContractDevice;
use Illuminate\Pagination\LengthAwarePaginator;

interface ContractServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Contract>
     */
    public function list(int $perPage, int $page): LengthAwarePaginator;

    /**
     * @param array{
     *     contract_number: string,
     *     partner_name: string,
     *     start_date: string,
     *     end_date?: string|null,
     *     status: string,
     *     notes?: string|null,
     * } $data
     */
    public function create(array $data): Contract;

    /**
     * @param array{
     *     contract_number?: string,
     *     partner_name?: string,
     *     start_date?: string,
     *     end_date?: string|null,
     *     status?: string,
     *     notes?: string|null,
     * } $data
     */
    public function update(Contract $contract, array $data): Contract;

    /**
     * @throws ConflictException when the contract still has devices assigned
     */
    public function delete(Contract $contract): void;

    /**
     * Put one physical unit of a device type on the contract.
     *
     * @param array{device_id: int, serial_number: string} $data
     */
    public function assignDevice(Contract $contract, array $data): ContractDevice;

    public function removeAssignment(ContractDevice $assignment): void;
}
