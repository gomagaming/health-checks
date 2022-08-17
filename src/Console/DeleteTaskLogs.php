<?php

namespace GomaGaming\HealthChecks\Console;

use GomaGaming\Logs\GomaGamingLogs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteTaskLogs extends Command
{
    protected string $beforeDate;

    protected array $allTaskLogFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:purge {daysBefore=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear old task logs.';

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
    public function handle()
    {
        GomaGamingLogs::info('Command DeleteTaskLogs started');

        $this->schedulerDisk = config('gomagaming-health-checks.service-scheduler-disk');

        $this->beforeDate = date('Y-m-d', strtotime('-'.$this->argument('daysBefore').' days'));

        $this->allTaskLogFiles = Storage::disk($this->schedulerDisk)->allFiles();

        $this->clearOldTaskLogs();

        GomaGamingLogs::info('Command DeleteTaskLogs ended');

        $this->line(
            date('Y-m-d H:i:s') . ' || ' . 
            config('gomagaming-health-checks.service-name') . 
            ' Service Task Logs Successfully cleared!');
    }

    protected function clearOldTaskLogs(): void
    {
        foreach ($this->allTaskLogFiles as $logFile) {
            $logFileDate = explode('-', explode('/', $logFile)[1], 2)[1];

            $daysBetween = (int) ceil((strtotime($logFileDate) - strtotime($this->beforeDate)) / 86400);

            // Delete log file if the log date is "older" than the $this->beforeDate
            if ($daysBetween < 0) {
                $this->line('<fg=green> - Task log successfully deleted: '.$logFile.'</>');
                Storage::disk($this->schedulerDisk)->delete($logFile);
            }
        }
    }
}
