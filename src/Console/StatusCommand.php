<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use IndieSystems\ApiDebugger\Models\ApiDebugSession;
use IndieSystems\ApiDebugger\Models\ApiLog;

class StatusCommand extends Command
{
    protected $signature = 'api-debugger:status';

    protected $description = 'Show API debugger status and active sessions';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=cyan>API Debugger Status</>');
        $this->line(str_repeat('-', 40));

        // Global status
        $enabled = config('api-debugger.enabled', false);
        $driver = config('api-debugger.driver', 'sync');

        $this->line('Enabled: ' . ($enabled ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('Driver: ' . $driver);
        $this->line('Retention: ' . config('api-debugger.retention.hours', 24) . ' hours');

        $this->newLine();

        // Active sessions
        $sessions = ApiDebugSession::active()
            ->withCount('logs')
            ->orderByDesc('created_at')
            ->get();

        if ($sessions->isEmpty()) {
            $this->line('<fg=yellow>No active debug sessions</>');
        } else {
            $this->line('<fg=cyan>Active Sessions (' . $sessions->count() . ')</>');

            $rows = $sessions->map(function ($session) {
                return [
                    $session->id,
                    $session->tenant_id ?? '-',
                    $session->user_id ?? '-',
                    $session->remainingMinutes() . ' min',
                    $session->logs_count,
                    $session->created_at->format('Y-m-d H:i'),
                ];
            });

            $this->table(
                ['ID', 'Tenant', 'User', 'Remaining', 'Logs', 'Started'],
                $rows
            );
        }

        $this->newLine();

        // Stats
        $totalLogs = ApiLog::count();
        $logsToday = ApiLog::whereDate('created_at', today())->count();
        $totalSessions = ApiDebugSession::count();

        $this->line('<fg=cyan>Statistics</>');
        $this->line("Total logs: {$totalLogs}");
        $this->line("Logs today: {$logsToday}");
        $this->line("Total sessions: {$totalSessions}");

        return self::SUCCESS;
    }
}
