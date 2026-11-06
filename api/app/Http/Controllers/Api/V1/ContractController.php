<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\Interfaces\ContractServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class ContractController extends Controller
{
    public function __construct(private readonly ContractServiceInterface $contracts)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return ContractResource::collection($this->contracts->list($this->perPage($request)));
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        return ContractResource::make($this->contracts->create($request->validated()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Contract $contract): ContractResource
    {
        return ContractResource::make($contract->load('assignments.device'));
    }

    public function update(UpdateContractRequest $request, Contract $contract): ContractResource
    {
        return ContractResource::make($this->contracts->update($contract, $request->validated()));
    }

    public function destroy(Contract $contract): Response
    {
        $this->contracts->delete($contract);

        return response()->noContent();
    }
}
