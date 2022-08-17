<?php

namespace GomaGaming\HealthChecks\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use GomaGaming\HealthChecks\Services\HealthChecksService;
use Illuminate\Http\JsonResponse;

class HealthChecksController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected HealthChecksService $healthChecksService;

    public function __construct(HealthChecksService $healthChecksService)
    {
        $this->healthChecksService = $healthChecksService;
    }

    public function runHealthChecks(): JsonResponse
    {
        return response()->json($this->healthChecksService->runHealthChecks(request()->only('args')), 200);
    }

    public function clearOldTasksLogs(): JsonResponse
    {
        \Log::debug(' -- request()->only() --', ['requestOnly' => request()->only('daysBefore')]);
        return response()->json($this->healthChecksService->clearOldTasksLogs(request()->only('daysBefore')), 200);
    }

    public function getServiceTasks(): JsonResponse
    {
        return response()->json($this->healthChecksService->getScheduledTasksData(), 200);
    }

}
