<?php

namespace GomaGaming\HealthChecks;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    protected $commandsToAvoidHealthChecks = [
        'package:discover',
        'vendor:publish',
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment() !== 'testing') {
            try {
                Event::listen(function (CommandStarting $event) {
                    if (! in_array($event->command, $this->commandsToAvoidHealthChecks)) {
                        Redis::set('health-check:task:start:'.$event->command, time());
                    }
                });

                Event::listen(function (CommandFinished $event) {
                    if (! in_array($event->command, $this->commandsToAvoidHealthChecks)) {
                        Redis::set('health-check:task:end:'.$event->command, time());
                    }
                });
            } catch (\RedisException $e) {
                // go silent
            }
        }
    }
}
