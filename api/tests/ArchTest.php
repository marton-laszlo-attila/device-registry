<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Services\ContractService;
use App\Services\DeviceService;
use App\Services\Interfaces\ContractServiceInterface;
use App\Services\Interfaces\DeviceServiceInterface;

arch()->preset()->php();
arch()->preset()->security();

arch('app uses strict types')->expect('App')->toUseStrictTypes();

arch('controllers depend on service interfaces, not implementations')
    ->expect([DeviceService::class, ContractService::class])
    ->toOnlyBeUsedIn('App\Providers');

arch('services are final')
    ->expect('App\Services')
    ->classes()
    ->toBeFinal();

arch('device service implements its interface')
    ->expect(DeviceService::class)
    ->toImplement(DeviceServiceInterface::class);

arch('contract service implements its interface')
    ->expect(ContractService::class)
    ->toImplement(ContractServiceInterface::class);

arch('services are HTTP agnostic')
    ->expect('App\Services')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Support\Facades\Request', 'request']);

arch('service interfaces are interfaces')
    ->expect('App\Services\Interfaces')
    ->toBeInterfaces();

arch('controllers do not query the database')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\Query']);

arch('business exceptions extend ApiException so the handler renders them')
    ->expect('App\Exceptions')
    ->classes()
    ->toExtend(ApiException::class)
    ->ignoring(ApiException::class);

arch('enums are string backed, because they are validated and stored as strings')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();
