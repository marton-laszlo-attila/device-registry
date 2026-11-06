<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContractStatus;
use App\Enums\DeviceCategory;
use App\Models\Contract;
use App\Models\Device;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a small, realistic demo data set.
     */
    public function run(): void
    {
        $laptop = Device::factory()->create([
            'manufacturer' => 'Dell',
            'model' => 'Latitude 5440',
            'category' => DeviceCategory::Laptop,
            'description' => '14" business laptop',
        ]);
        $monitor = Device::factory()->create([
            'manufacturer' => 'Samsung',
            'model' => 'ViewFinity S6',
            'category' => DeviceCategory::Monitor,
            'description' => '27" QHD monitor',
        ]);
        $printer = Device::factory()->create([
            'manufacturer' => 'Brother',
            'model' => 'HL-L5210DN',
            'category' => DeviceCategory::Printer,
            'description' => null,
        ]);
        Device::factory()->create([
            'manufacturer' => 'Cisco',
            'model' => 'Catalyst 1300-24P',
            'category' => DeviceCategory::Network,
            'description' => '24-port PoE switch',
        ]);

        $leasing = Contract::factory()->create([
            'contract_number' => 'CT-2026-0001',
            'partner_name' => 'Acme Kft.',
            'start_date' => '2026-01-01',
            'end_date' => '2028-12-31',
            'status' => ContractStatus::Active,
            'notes' => 'Three-year leasing contract for office equipment.',
        ]);
        $support = Contract::factory()->create([
            'contract_number' => 'CT-2026-0002',
            'partner_name' => 'Globex Zrt.',
            'start_date' => '2026-03-01',
            'end_date' => null,
            'status' => ContractStatus::Active,
            'notes' => null,
        ]);
        Contract::factory()->create([
            'contract_number' => 'CT-2026-0003',
            'partner_name' => 'Initech Bt.',
            'start_date' => '2026-12-01',
            'end_date' => '2027-11-30',
            'status' => ContractStatus::Draft,
            'notes' => 'Waiting for signature.',
        ]);

        $leasing->devices()->attach($laptop, ['serial_number' => 'DL5440-0001']);
        $leasing->devices()->attach($laptop, ['serial_number' => 'DL5440-0002']);
        $leasing->devices()->attach($monitor, ['serial_number' => 'SVF6-1001']);
        $support->devices()->attach($laptop, ['serial_number' => 'DL5440-0003']);
        $support->devices()->attach($printer, ['serial_number' => 'BHL5210-77']);
    }
}
