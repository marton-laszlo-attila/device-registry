<?php

declare(strict_types=1);

namespace App\Services\Interfaces;

use App\Exceptions\ConflictException;
use App\Models\Device;
use Illuminate\Pagination\LengthAwarePaginator;

interface DeviceServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Device>
     */
    public function list(int $perPage): LengthAwarePaginator;

    /**
     * @param array{manufacturer: string, model: string, category: string, description?: string|null} $data
     */
    public function create(array $data): Device;

    /**
     * @param array{manufacturer?: string, model?: string, category?: string, description?: string|null} $data
     */
    public function update(Device $device, array $data): Device;

    /**
     * @throws ConflictException when the device type is assigned to a contract
     */
    public function delete(Device $device): void;
}
