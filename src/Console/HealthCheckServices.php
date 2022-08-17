<?php

namespace GomaGaming\HealthChecks\Console;

use GomaGaming\HealthChecks\Services\HealthChecksService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class HealthCheckServices extends Command
{
    protected HealthChecksService $healthChecksService;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health-check:services {--pC|pingCheck}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Health Check - Monitor the health of services ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(HealthChecksService $healthChecksService)
    {
        $this->healthChecksService = $healthChecksService;

        $this->serviceName = config('gomagaming-health-checks.service-name');

        $this->monitorGeneralServices();

        $this->monitorTasks();

        $this->monitorServices();
    }

    protected function loginAdmin()
    {
        Auth::login(User::first());
    }

    protected function monitorGeneralServices()
    {
        if ($this->option('pingCheck')) 
        {
            $this->loginAdmin();

            $this->healthChecksService->clearChecks();

            $this->healthChecksService->generalServicesChecks();

            $this->runChecks();

            $this->healthChecksService->saveResultFileInRedis('Services');
        }
    }

    protected function monitorTasks()
    {
        $this->healthChecksService->clearChecks();

        $this->healthChecksService->tasksChecks();

        $this->runChecks();

        $this->healthChecksService->saveResultFileInRedis(ucfirst($this->serviceName) . ' Tasks');
    }

    protected function monitorServices()
    {
        $this->healthChecksService->clearChecks();

        $this->healthChecksService->serviceChecks();

        $this->runChecks();

        $this->healthChecksService->saveResultFileInRedis(ucfirst($this->serviceName) . ' Status');
    }

    protected function runChecks()
    {
        $this->call('health:check');
    }
}
