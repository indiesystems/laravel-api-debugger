<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;

class EnableDebugCommand extends Command
{
    protected $signature = 'api-debugger:enable
                            {--tenant= : Tenant ID to debug}
                            {--user= : User ID to debug}
                            {--duration= : Duration in minutes (default from config)}';

    protected $description = 'Enable API debugging for a tenant or user';

    public function handle(ApiDebuggerService $debugger): int
    {
        $tenantId = $this->option('tenant');
        $userId = $this->option('user');
        $duration = $this->option('duration');

        if (!$tenantId && !$userId) {
            $this->error('Please specify --tenant or --user');
            return self::FAILURE;
        }

        if ($tenantId && $userId) {
            $session = $debugger->enableForTenantUser($tenantId, (int) $userId, $duration ? (int) $duration : null);
            $this->info("Debugging enabled for tenant '{$tenantId}' and user #{$userId}");
        } elseif ($tenantId) {
            $session = $debugger->enableForTenant($tenantId, $duration ? (int) $duration : null);
            $this->info("Debugging enabled for tenant '{$tenantId}'");
        } else {
            $session = $debugger->enableForUser((int) $userId, $duration ? (int) $duration : null);
            $this->info("Debugging enabled for user #{$userId}");
        }

        $this->line("Session expires at: {$session->expires_at->format('Y-m-d H:i:s')}");
        $this->line("Remaining: {$session->remainingMinutes()} minutes");

        return self::SUCCESS;
    }
}
