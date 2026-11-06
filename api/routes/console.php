<?php

declare(strict_types=1);

use App\Models\ApiLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps the API log from growing without limit (retention: config/api_logging.php).
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();
