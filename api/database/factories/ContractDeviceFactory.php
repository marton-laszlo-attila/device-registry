<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractDevice>
 */
class ContractDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'device_id' => Device::factory(),
            'serial_number' => fake()->unique()->bothify('SN-??########'),
        ];
    }
}
