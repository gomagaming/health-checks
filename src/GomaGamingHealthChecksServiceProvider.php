<?php 

namespace GomaGaming\HealthChecks;

use Illuminate\Support\ServiceProvider;
use GomaGaming\HealthChecks\Console\DeleteTaskLogs;
use GomaGaming\HealthChecks\Console\HealthCheckServices;
use Illuminate\Support\Facades\Route;

class GomaGamingHealthChecksServiceProvider extends ServiceProvider
{

    public function boot()
    {
        if (app() instanceof \Illuminate\Foundation\Application) {
            $this->publishes([
                __DIR__ . '/../config/gomagaming-health-checks.php' => config_path('gomagaming-health-checks.php'),
                __DIR__ . '/../config/health.php' => config_path('health.php'),
            ], 'gomagaming-health-checks');
        }

        $this->commands([
            DeleteTaskLogs::class,
            HealthCheckServices::class,
        ]);

        $this->registerRoutes();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gomagaming-health-checks.php', 'gomagaming-health-checks');
        $this->mergeConfigFrom(__DIR__.'/../config/health.php', 'health');

        $this->app->register(EventServiceProvider::class);
    }

    protected function registerRoutes()
    {
        Route::group(['prefix' => 'gg-health-checks'], function () {
            Route::group(['prefix' => 'api'], function () {

                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

            });
        });
    }

}
