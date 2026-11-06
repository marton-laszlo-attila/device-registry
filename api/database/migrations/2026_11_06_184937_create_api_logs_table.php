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
        Schema::create('api_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('method', 10);
            $table->text('url');
            $table->unsignedSmallInteger('status_code');
            $table->json('request_headers');
            $table->longText('request_body')->nullable();
            $table->longText('response_body')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
