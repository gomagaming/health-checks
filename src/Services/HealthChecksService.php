<?php

namespace GomaGaming\HealthChecks\Services;

use GomaGaming\HealthChecks\Checks\TaskCheck;
use GomaGaming\HealthChecks\Checks\CustomCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\PingCheck;
use Spatie\Health\Facades\Health;
use Spatie\Health\ResultStores\InMemoryHealthResultStore;
use Illuminate\Support\Str;

class HealthChecksService 
{
    protected $accessToken = null;
    protected String $schedulerDisk;

    public function __construct(InMemoryHealthResultStore $resultsInMemory)
    {
        app()->make(\Illuminate\Contracts\Console\Kernel::class);
        $this->schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);

        $this->resultsInMemory = $resultsInMemory;
        $this->schedulerDisk = config('gomagaming-health-checks.service-scheduler-disk');
    }

    public function getScheduledTasksData(): array
    {
        $commands = $this->getScheduleCommands();

        foreach ($commands as &$command) {
            $command['last_started_time'] =
                Redis::get('health-check:task:start:'.$command['signature']) ?
                date('Y-m-d H:i:s', Redis::get('health-check:task:start:'.$command['signature'])) :
                null;
            $command['last_started_time_unix'] = Redis::get('health-check:task:start:'.$command['signature']);
            $command['last_ended_time'] =
                Redis::get('health-check:task:end:'.$command['signature']) ?
                date('Y-m-d H:i:s', Redis::get('health-check:task:end:'.$command['signature'])) :
                null;
            $command['last_ended_time_unix'] = Redis::get('health-check:task:end:'.$command['signature']);

            if (Storage::disk($this->schedulerDisk)->exists($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('-2 days')))) {
                $command['logs-from-2-days-before'] = Storage::disk($this->schedulerDisk)->get($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('-2 days')));
            }
            if (Storage::disk($this->schedulerDisk)->exists($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('-1 days')))) {
                $command['logs-from-1-day-before'] = Storage::disk($this->schedulerDisk)->get($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('-1 days')));
            }
            if (Storage::disk($this->schedulerDisk)->exists($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('today')))) {
                $command['logs-from-today'] = Storage::disk($this->schedulerDisk)->get($command['command-class-name'].'/log-'.date('Y-m-d', strtotime('today')));
            }
        }

        return $commands;
    }

    public function getScheduleCommands(): array
    {
        $commands = [];
        foreach ($this->schedule->events() as $event) {
            $commands[] = [
                'label' => explode(' - ', $event->description)[0],
                'signature' => explode(' ', $event->command)[2],
                'expression' => $event->expression,
                'command-class-name' => explode('/', $event->output)[3],
            ];
        }

        return $commands;
    }

    public function serviceChecks(): void
    {
        $checks = [
            DatabaseCheck::new(),
            RedisCheck::new()->connectionName('default'),
            CacheCheck::new(),
            DebugModeCheck::new(),
            EnvironmentCheck::new()->expectEnvironment('production'),
            UsedDiskSpaceCheck::new(),
        ];
        
        if (app()->providerIsLoaded(HorizonServiceProvider::class)) {
            array_push($checks, HorizonCheck::new());
        }

        $this->healthChecks($checks);
    }

    public function tasksChecks(): void
    {
        $tasks = [];
        foreach ($this->getScheduleCommands() as $command) {
            $tasks[] = $this->newTaskCheck($command);
        }

        $this->healthChecks($tasks);
    }

    public function clearChecks(): void
    {
        Health::clearChecks();
    }

    public function runChecks(): void
    {
        Artisan::call('health:check');
    }

    public function runHealthChecks($args = [])
    {
        $optionalArgs = !empty($args) && isset($args['args']) ? implode(' ', $args['args']) : '';
        
        return $this->runTaskBySignatureWithArguments('HealthCheckServices', 'health-check:services ', $optionalArgs);
    }

    public function clearOldTasksLogs($args = ['daysBefore' => 3])
    {
        return $this->runTaskBySignatureWithArguments('DeleteTaskLogs', 'schedule:purge ', $args['daysBefore']);
    }

    protected function runTaskBySignatureWithArguments($taskClassName, $signature, $args)
    {
        $logName = 'log-'.date('Y-m-d');
        $folder = Str::kebab($taskClassName);

        Storage::disk($this->schedulerDisk)->makeDirectory($folder);

        $taskLogsPath = $folder.'/'.$logName;

        Artisan::call($signature . $args);
        $artisanCommandOutput = Artisan::output();
        Storage::disk($this->schedulerDisk)->put($taskLogsPath, $artisanCommandOutput);

        return $artisanCommandOutput;
    }

    public function healthChecks($tasks): void
    {
        Health::checks($tasks);
    }

    public function saveResultFileInRedis($name): void
    {
        $results = json_decode(Redis::get('health-check:results'), true);

        $results[$name] = [
            'finishedAt' => $this->resultsInMemory->latestResults()->finishedAt->timestamp,
            'checkResults' => json_decode($this->resultsInMemory->latestResults()->storedCheckResults->toJson(), true)
        ];

        Redis::set('health-check:results', json_encode($results));
    }

    protected function newTaskCheck($data): Check
    {
        return TaskCheck::new()->label($data['label'])
                        ->name($data['signature'])
                        ->warnWhenMinutesHavePassed($this->getWarningMinutesFromExpression($data['expression']))
                        ->failWhenMinutesHavePassed($this->getFailMinutesFromExpression($data['expression']));
    }

    public function generalServicesChecks(): void
    {
        $sportsbookApiAuthCheck = $this->pingSportsbookAPIAuth();
        $sportsbookApiUsersCheck = $this->pingSportsbookAPIUsers();

        $pingCheckServices = [
            PingCheck::new()->name('Sportsbook API Gateway')->label('Sportsbook API Gateway')->url(config('gomagaming-health-checks.ping-urls.api-gateway')),
            $sportsbookApiAuthCheck,
            $sportsbookApiUsersCheck,
            PingCheck::new()->name('Sportsbook API Settings')->label('Sportsbook API Settings')->url(config('gomagaming-health-checks.ping-urls.api-settings').'/user?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Favorites')->label('Sportsbook API Favorites')->url(config('gomagaming-health-checks.ping-urls.api-favorites').'?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Betting')->label('Sportsbook API Betting')->url(config('gomagaming-health-checks.ping-urls.api-betting').'/betslip/suggestions?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Social')->label('Sportsbook API Social')->url(config('gomagaming-health-checks.ping-urls.api-social').'/friends?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Notifications')->label('Sportsbook API Notifications')->url(config('gomagaming-health-checks.ping-urls.api-notifications').'?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Sentry')->label('Sportsbook API Sentry')->url(config('gomagaming-health-checks.ping-urls.api-sentry').'?jwt='.$this->accessToken),
            PingCheck::new()->name('Sportsbook API Gateway CMS')->label('Sportsbook API Gateway CMS')->url(config('gomagaming-health-checks.ping-urls.api-gateway-cms')),
            PingCheck::new()->name('Sportsbook Client CMS')->label('Sportsbook Client CMS')->url(config('gomagaming-health-checks.ping-urls.client-cms'))->timeout(3),
            PingCheck::new()->name('Sportsbook Admin CMS')->label('Sportsbook Admin CMS')->url(config('gomagaming-health-checks.ping-urls.admin-cms'))->timeout(3),

        ];

        if (env('APP_ENV') === 'production') {
            array_push(
                $pingCheckServices,
                PingCheck::new()->name('Sportsbook Web')->label('Sportsbook Web')->url(config('gomagaming-health-checks.ping-urls.web'))
            );
        }

        header('token: 9g7rp9760c33c6g1f19mn5ut3asd67');

        array_push(
            $pingCheckServices,
            PingCheck::new()
                ->name('Sportsbook Socket')
                ->label('Sportsbook Socket')
                ->url(config('gomagaming-health-checks.ping-urls.api-gateway').'?jwt='.$this->accessToken.'&EIO=4&transport=websocket')
        );

        $this->healthChecks($pingCheckServices);
    }

    protected function pingSportsbookAPIAuth(): Check
    {
        $custom = CustomCheck::new()
                             ->type('ping')
                             ->label('Sportsbook API Auth')
                             ->name('Sportsbook API Auth');
        try {
            $response = Http::post(config('gomagaming-health-checks.ping-urls.api-auth'), [
                'device_uuid' => 'healthcheckscommand',
                'device_type' => 'web',
                'type' => 'anonymous',
            ]);
        } catch (\Throwable $th) {
            return $custom->error();
        }

        if (! $response->successful()) {
            return $custom->error();
        }

        $this->accessToken = $response->json('access_token');

        return $custom->success();
    }

    protected function pingSportsbookAPIUsers(): Check
    {
        $custom = CustomCheck::new()
                             ->type('ping')
                             ->label('Sportsbook API Users')
                             ->name('Sportsbook API Users');
        try {
            $response = Http::withToken($this->accessToken)->post(config('gomagaming-health-checks.ping-urls.api-users').'/in-app', ['phone_numbers' => ['111111111']]);
        } catch (\Throwable $th) {
            return $custom->error();
        }

        if (! $response->successful()) {
            return $custom->error();
        }

        return $custom->success();
    }

    protected function getWarningMinutesFromExpression($expression): int
    {
        switch ($expression) {
            case '*/10 * * * *':
                return 20;
            case '* * * * *':
                return 20;
            case '0 */2 * * *':
                return 160;
            case '0 * * * *':
                return 100;
            case '0 0 * * *':
                return 1800;
            case '0 4 * * *':
                return 1800;
            case '*/5 * * * *':
                return 20;
            default:
                return 60;
        }
    }

    protected function getFailMinutesFromExpression($expression): int
    {
        switch ($expression) {
            case '*/10 * * * *':
                return 40;
            case '* * * * *':
                return 30;
            case '0 */2 * * *':
                return 200;
            case '0 * * * *':
                return 120;
            case '0 0 * * *':
                return 2000;
            case '0 4 * * *':
                return 2000;
            case '*/5 * * * *':
                return 30;
            default:
                return 90;
        }
    }
}
