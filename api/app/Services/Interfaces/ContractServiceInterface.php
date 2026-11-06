<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\Exceptions\ConflictException;
use App\Models\Contract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContractServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Contract>
     */
    public function list(int $perPage): LengthAwarePaginator;

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
}
