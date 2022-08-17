<?php

use Illuminate\Support\Facades\Route;

use GomaGaming\HealthChecks\Http\Controllers\HealthChecksController;

Route::get('run-health-checks', [HealthChecksController::class, 'runHealthChecks']);

Route::get('clear-old-tasks-logs', [HealthChecksController::class, 'clearOldTasksLogs']);

Route::get('get-tasks', [HealthChecksController::class, 'getServiceTasks']);

