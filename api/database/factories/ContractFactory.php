<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 years', 'now');
        $endDate = fake()->boolean(70) ? fake()->dateTimeBetween($startDate, '+2 years') : null;

        return [
            'contract_number' => fake()->unique()->numerify('CT-####-#####'),
            'partner_name' => fake()->company(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate?->format('Y-m-d'),
            'status' => fake()->randomElement(ContractStatus::cases()),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
