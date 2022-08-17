<?php

namespace GomaGaming\HealthChecks\Checks;

use Exception;
use Illuminate\Support\Facades\Redis;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class TaskCheck extends Check
{
    protected string $connectionName = 'default';

    protected int $warningMinutes = 2;

    protected int $failMinutes = 5;

    protected $lastRunTime;

    protected int $currentTime;

    public function connectionName(string $connectionName): self
    {
        $this->connectionName = $connectionName;

        return $this;
    }

    public function warnWhenMinutesHavePassed(int $minutes): self
    {
        $this->warningMinutes = $minutes;

        return $this;
    }

    public function failWhenMinutesHavePassed(int $minutes): self
    {
        $this->failMinutes = $minutes;

        return $this;
    }

    public function run(): Result
    {
        $this->currentTime = time();
        $this->failMinutes *= 60;
        $this->warningMinutes *= 60;

        $result = Result::make()->meta([
            'connection_name' => $this->connectionName,
        ]);

        try {
            $this->lastRunTime = $this->getLastRunningTime();
        } catch (Exception $exception) {
            return $result->failed("An exception occurred when connecting to Redis: `{$exception->getMessage()}`");
        }

        if (! $this->lastRunTime || ! $this->isRunning()) {
            return $result->failed("The task isn't running.");
        }

        if ($this->isRunningForTooLong()) {
            return $result->failed('The task is running for too long.');
        }

        return $result->ok()->shortSummary('Last run: '.date('Y-m-d H:i:s', $this->lastRunTime));
    }

    protected function getLastRunningTime(): null|string
    {
        return Redis::connection($this->connectionName)->get('health-check:task:start:'.$this->name);
    }

    protected function isRunning()
    {
        if ($this->isLongProcess()) {
            return true;
        }

        return ($this->lastRunTime + $this->failMinutes) > $this->currentTime;
    }

    protected function isRunningForTooLong()
    {
        if ($this->isLongProcess()) {
            return false;
        }

        return ($this->lastRunTime + $this->warningMinutes) <= $this->currentTime;
    }

    protected function isLongProcess()
    {
        return Redis::connection($this->connectionName)->get('health-check:task:start:long-process:'.$this->name);
    }
}
