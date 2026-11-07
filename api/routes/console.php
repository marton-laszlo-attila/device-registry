<?php

declare(strict_types=1);

use App\Models\ApiLog;
use Illuminate\Support\Facades\Schedule;

// Keeps the API log from growing without limit (retention: config/api_logging.php).
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();
