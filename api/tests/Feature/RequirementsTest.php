<?php

declare(strict_types=1);

/*
 * Requirement matrix of docs/task.txt. Every test walks one requirement through
 * the public HTTP API only (no factories, no direct database access), so it
 * shows that the endpoints work together, not just one by one.
 *
 * Requirements covered elsewhere, by file:
 *  - "Egységes hibakezelés"            -> ErrorHandlingTest
 *  - "API forgalom logolása"           -> ApiLoggingTest
 *  - "OpenAPI dokumentáció Basic Auth" -> ApiDocsTest
 *  - "Migrációkból felállítható"       -> SeederTest (and every test: RefreshDatabase runs the migrations)
 */

it('task: creates, lists, shows, updates and deletes a device', function (): void {
    $id = $this->createDeviceViaApi();

    $this->getJson('/api/v1/devices')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/devices/{$id}")->assertOk()->assertJsonPath('data.model', 'Latitude 5440');

    $this->patchJson("/api/v1/devices/{$id}", ['model' => 'Latitude 5450'])->assertOk();
    $this->getJson("/api/v1/devices/{$id}")->assertJsonPath('data.model', 'Latitude 5450');

    $this->deleteJson("/api/v1/devices/{$id}")->assertNoContent();
    $this->getJson("/api/v1/devices/{$id}")->assertNotFound();
    $this->getJson('/api/v1/devices')->assertJsonCount(0, 'data');
});

it('task: creates, lists, shows, updates and deletes a contract', function (): void {
    $id = $this->createContractViaApi();

    $this->getJson('/api/v1/contracts')->assertOk()->assertJsonPath('data.0.id', $id);
    $this->getJson("/api/v1/contracts/{$id}")->assertOk()->assertJsonPath('data.partner_name', 'Acme Kft.');

    $this->putJson("/api/v1/contracts/{$id}", ['partner_name' => 'Globex Zrt.'])->assertOk();
    $this->getJson("/api/v1/contracts/{$id}")->assertJsonPath('data.partner_name', 'Globex Zrt.');

    $this->deleteJson("/api/v1/contracts/{$id}")->assertNoContent();
    $this->getJson("/api/v1/contracts/{$id}")->assertNotFound();
});

it('task: shows the devices on the contract detail but not in the contract list', function (): void {
    $contractId = $this->createContractViaApi();
    $this->assignDeviceViaApi($contractId, $this->createDeviceViaApi(), 'SN-0001');

    $this->getJson("/api/v1/contracts/{$contractId}")
        ->assertOk()
        ->assertJsonCount(1, 'data.devices')
        ->assertJsonPath('data.devices.0.serial_number', 'SN-0001')
        ->assertJsonPath('data.devices.0.device.model', 'Latitude 5440');

    $this->getJson('/api/v1/contracts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissingPath('data.0.devices');
});

it('task: puts one device type on several contracts, each unit with its own serial number (N:N)', function (): void {
    $deviceId = $this->createDeviceViaApi();
    $first = $this->createContractViaApi(['contract_number' => 'CT-1']);
    $second = $this->createContractViaApi(['contract_number' => 'CT-2']);

    $this->assignDeviceViaApi($first, $deviceId, 'SN-0001');
    $this->assignDeviceViaApi($first, $deviceId, 'SN-0002');
    $this->assignDeviceViaApi($second, $deviceId, 'SN-0003');

    $this->getJson("/api/v1/contracts/{$first}")
        ->assertJsonCount(2, 'data.devices')
        ->assertJsonPath('data.devices.0.device.id', $deviceId)
        ->assertJsonPath('data.devices.1.device.id', $deviceId);
    $this->getJson("/api/v1/contracts/{$second}")
        ->assertJsonCount(1, 'data.devices')
        ->assertJsonPath('data.devices.0.serial_number', 'SN-0003');

    // A physical unit (serial number) cannot be on two contracts at once.
    $this->postJson("/api/v1/contracts/{$second}/devices", ['device_id' => $deviceId, 'serial_number' => 'SN-0001'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serial_number']);
});

it('task: adds a device to a contract and takes it off again', function (): void {
    $deviceId = $this->createDeviceViaApi();
    $contractId = $this->createContractViaApi();
    $assignmentId = $this->assignDeviceViaApi($contractId, $deviceId, 'SN-0001');

    // While the unit is on the contract, neither the contract nor the device type can be deleted.
    $this->deleteJson("/api/v1/contracts/{$contractId}")->assertConflict();
    $this->deleteJson("/api/v1/devices/{$deviceId}")->assertConflict();

    $this->deleteJson("/api/v1/contracts/{$contractId}/devices/{$assignmentId}")->assertNoContent();
    $this->getJson("/api/v1/contracts/{$contractId}")->assertJsonCount(0, 'data.devices');

    // Taken off: the same serial number can be added again.
    $again = $this->assignDeviceViaApi($contractId, $deviceId, 'SN-0001');

    // Once the last unit is gone, both records are free to delete.
    $this->deleteJson("/api/v1/contracts/{$contractId}/devices/{$again}")->assertNoContent();
    $this->deleteJson("/api/v1/contracts/{$contractId}")->assertNoContent();
    $this->deleteJson("/api/v1/devices/{$deviceId}")->assertNoContent();
});
