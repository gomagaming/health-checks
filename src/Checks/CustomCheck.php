<?php

namespace GomaGaming\HealthChecks\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class CustomCheck extends Check
{
    protected $success = false;

    protected $error = false;

    protected $type = null;

    public function success(): self
    {
        $this->success = true;

        return $this;
    }

    public function error(): self
    {
        $this->error = true;

        return $this;
    }

    public function type($type): self
    {
        $this->type = $type;

        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();

        if ($this->error) {
            return $result->failed($this->getFailMessage());
        }

        if ($this->success) {
            return $result->ok()->shortSummary($this->getSuccessMessage());
        }
    }

    protected function getFailMessage(): string
    {
        switch ($this->type) {
            case 'ping':
                return 'Pinging '.$this->name.' failed.';
            default:
                return 'The '.$this->name." isn't operational.";
        }
    }

    protected function getSuccessMessage(): string
    {
        switch ($this->type) {
            case 'ping':
                return 'reachable';
            default:
                return 'The '.$this->name.' is operational.';
        }
    }
}
