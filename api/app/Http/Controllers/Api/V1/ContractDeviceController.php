<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeviceRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Services\Interfaces\ContractServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Adds device units to a contract and removes them.
 */
final class ContractDeviceController extends Controller
{
    public function __construct(private readonly ContractServiceInterface $contracts)
    {
    }

    public function store(AssignDeviceRequest $request, Contract $contract): JsonResponse
    {
        return AssignmentResource::make($this->contracts->assignDevice($contract, $request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The assignment is resolved through the contract (scoped binding),
     * so an assignment of another contract answers 404.
     */
    public function destroy(Contract $contract, ContractDevice $assignment): Response
    {
        $this->contracts->removeAssignment($assignment);

        return response()->noContent();
    }
}
