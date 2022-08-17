# GomaGaming Health Checks for Laravel

## Description

-   GomaGaming Health Checks allows you to run the "spatie/laravel-health" by calling one simple endpoint, this feature will also save the Tasks Results as well as the Health Checks Results in Redis.
-   Create/Update logs for all the Service active/scheduled Tasks.
-   Clear those logs by calling another simple endpoint, giving the days prior you want to clear it.
-   Get Tasks Data - Task command, label, description, cron expression, last started time and last ended time.

## Laravel Version Compatibility

-   Laravel `>= 9.x.x` on PHP `>= 8.0`

## Installation

```
    composer require gomagaming/health-checks
```

## Configuration files

```
    php artisan vendor:publish --tag=gomagaming-health-checks
```

```
    php artisan vendor:publish --tag=health-config
```

On config/health.php and bootstrap/health.php:

-   Inside the "result_stores" array, comment all values but "Spatie\Health\ResultStores\InMemoryHealthResultStore::class".

On config/filesystems.php:

-   Add the following disk to the "disks" array:

```
env('HEALTH_CHECKS_SCHEDULER_DISK', 'scheduler') => [
    'driver' => 'local',
    'root' => storage_path('logs/' . env('HEALTH_CHECKS_SCHEDULER_DISK', 'scheduler')),
]
```

## Usage

TODO

## Tests

TODO

```

```
