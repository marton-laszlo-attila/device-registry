<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeviceRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Services\Interfaces\ContractServiceInterface;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Adds device units to a contract and removes them.
 */
#[Group('Contract devices', weight: 3)]
final class ContractDeviceController extends Controller
{
    public function __construct(private readonly ContractServiceInterface $contracts)
    {
    }

    /**
     * Add a device to a contract.
     *
     * The serial number identifies the physical unit and is unique across all contracts.
     * The same device type can be added several times with different serial numbers.
     */
    #[DocumentedResponse(409, 'A concurrent request took the serial number.', type: 'array{message: string}')]
    public function store(AssignDeviceRequest $request, Contract $contract): JsonResponse
    {
        return AssignmentResource::make($this->contracts->assignDevice($contract, $request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Remove a device from a contract.
     *
     * The assignment is resolved through the contract (scoped binding),
     * so an assignment of another contract answers 404.
     */
    public function destroy(Contract $contract, ContractDevice $assignment): Response
    {
        $this->contracts->removeAssignment($assignment);

        return response()->noContent();
    }
}
