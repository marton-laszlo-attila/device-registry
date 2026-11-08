<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\Device;
use App\Services\Interfaces\DeviceServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class DeviceService implements DeviceServiceInterface
{
    public function list(int $perPage, int $page): LengthAwarePaginator
    {
        // The page size goes into the pagination links, otherwise following one falls back to the default size.
        return Device::query()->orderBy('id')->paginate($perPage, page: $page)->appends(['per_page' => $perPage]);
    }

    public function create(array $data): Device
    {
        return Device::query()->create($data);
    }

    public function update(Device $device, array $data): Device
    {
        $device->update($data);

        return $device;
    }

    public function delete(Device $device): void
    {
        if ($device->assignments()->exists()) {
            throw ConflictException::deviceInUse();
        }

        $device->delete();
    }
}
