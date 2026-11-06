<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\Interfaces\DeviceServiceInterface;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Device types: a manufacturer and model pair, not a physical unit.
 */
#[Group('Devices', weight: 1)]
final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceServiceInterface $devices)
    {
    }

    /**
     * List device types.
     *
     * Paginated, ordered by id.
     */
    #[QueryParameter('per_page', description: 'Items per page, clamped to 1–100.', type: 'int', default: 15)]
    #[QueryParameter('page', description: 'Page number.', type: 'int', default: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->devices->list($this->perPage($request)));
    }

    /**
     * Create a device type.
     *
     * The manufacturer and model pair must be unique.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        return DeviceResource::make($this->devices->create($request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a device type.
     */
    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device);
    }

    /**
     * Update a device type.
     *
     * PUT and PATCH both accept partial updates: only the sent fields change.
     */
    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        return DeviceResource::make($this->devices->update($device, $request->validated()));
    }

    /**
     * Delete a device type.
     *
     * Not possible while the device type is assigned to any contract.
     */
    #[DocumentedResponse(409, 'The device type is assigned to a contract.', type: 'array{message: string}')]
    public function destroy(Device $device): Response
    {
        $this->devices->delete($device);

        return response()->noContent();
    }
}
