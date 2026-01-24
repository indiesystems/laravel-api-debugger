<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;

class PruneLogsCommand extends Command
{
    protected $signature = 'api-debugger:prune
                            {--expired-sessions : Only deactivate expired sessions without deleting logs}';

    protected $description = 'Clean up old API debug logs and expired sessions';

    public function handle(ApiDebuggerService $debugger): int
    {
        // Always deactivate expired sessions
        $expiredCount = $debugger->deactivateExpiredSessions();
        $this->line("Deactivated {$expiredCount} expired session(s)");

        if ($this->option('expired-sessions')) {
            return self::SUCCESS;
        }

        // Delete old logs
        $deletedLogs = $debugger->pruneOldLogs();
        $this->line("Deleted {$deletedLogs} old log(s)");

        // Clean up inactive sessions and their logs
        $cleanedUp = $debugger->pruneInactiveSessions();
        $this->line("Cleaned up {$cleanedUp} log(s) from inactive sessions");

        $this->info('Cleanup complete');

        return self::SUCCESS;
    }
}
