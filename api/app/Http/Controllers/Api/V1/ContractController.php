<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\Interfaces\ContractServiceInterface;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as DocumentedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Contracts', weight: 2)]
final class ContractController extends Controller
{
    public function __construct(private readonly ContractServiceInterface $contracts)
    {
    }

    /**
     * List contracts.
     *
     * Paginated, ordered by id. The devices of the contracts are not included.
     */
    #[QueryParameter('per_page', description: 'Items per page, clamped to 1–100.', type: 'int', default: 15)]
    #[QueryParameter('page', description: 'Page number, at least 1.', type: 'int', default: 1)]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        return ContractResource::collection($this->contracts->list($perPage, $this->page($request, $perPage)));
    }

    /**
     * Create a contract.
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        return ContractResource::make($this->contracts->create($request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Get a contract with its devices.
     *
     * Every device unit on the contract is listed with its serial number and assignment id.
     */
    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($contract->load('assignments.device'));
    }

    /**
     * Update a contract.
     *
     * PUT and PATCH both accept partial updates: only the sent fields change.
     */
    public function update(UpdateContractRequest $request, Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->update($contract, $request->validated()));
    }

    /**
     * Delete a contract.
     *
     * Not possible while devices are assigned to the contract.
     */
    #[DocumentedResponse(409, 'The contract has devices assigned.', type: 'array{message: string}')]
    public function destroy(Contract $contract): Response
    {
        $this->contracts->delete($contract);

        return response()->noContent();
    }
}
