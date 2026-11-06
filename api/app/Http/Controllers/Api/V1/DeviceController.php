<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\Interfaces\DeviceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class DeviceController extends Controller
{
    public function __construct(private readonly DeviceServiceInterface $devices)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeviceResource::collection($this->devices->list($this->perPage($request)));
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        return DeviceResource::make($this->devices->create($request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device);
    }

    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        return DeviceResource::make($this->devices->update($device, $request->validated()));
    }

    public function destroy(Device $device): Response
    {
        $this->devices->delete($device);

        return response()->noContent();
    }
}
