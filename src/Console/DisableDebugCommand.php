<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;
use IndieSystems\ApiDebugger\Models\ApiDebugSession;

class DisableDebugCommand extends Command
{
    protected $signature = 'api-debugger:disable
                            {--tenant= : Tenant ID to stop debugging}
                            {--user= : User ID to stop debugging}
                            {--all : Disable all active sessions}';

    protected $description = 'Disable API debugging for a tenant or user';

    public function handle(ApiDebuggerService $debugger): int
    {
        $tenantId = $this->option('tenant');
        $userId = $this->option('user');
        $all = $this->option('all');

        if ($all) {
            $count = ApiDebugSession::active()->update(['active' => false]);
            $this->info("Disabled {$count} active debug session(s)");
            return self::SUCCESS;
        }

        if (!$tenantId && !$userId) {
            $this->error('Please specify --tenant, --user, or --all');
            return self::FAILURE;
        }

        if ($tenantId) {
            $count = $debugger->disableForTenant($tenantId);
            $this->info("Disabled {$count} session(s) for tenant '{$tenantId}'");
        }

        if ($userId) {
            $count = $debugger->disableForUser((int) $userId);
            $this->info("Disabled {$count} session(s) for user #{$userId}");
        }

        return self::SUCCESS;
    }
}
