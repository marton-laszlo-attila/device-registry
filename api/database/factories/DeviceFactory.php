<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceCategory;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manufacturer' => fake()->randomElement(['Dell', 'HP', 'Lenovo', 'Apple', 'Samsung', 'Cisco', 'Brother']),
            'model' => fake()->unique()->bothify('??-####'),
            'category' => fake()->randomElement(DeviceCategory::cases()),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
