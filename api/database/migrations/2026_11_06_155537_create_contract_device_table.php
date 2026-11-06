<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contract_device', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 100)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_device');
    }
};
