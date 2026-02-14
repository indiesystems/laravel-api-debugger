<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'api-debugger:install
                            {--no-migration : Skip running migrations}';

    protected $description = 'Install the API Debugger package';

    public function handle(): int
    {
        $this->info('Installing API Debugger...');
        $this->newLine();

        $this->publishConfig();

        if (!$this->option('no-migration')) {
            $this->runMigrations();
        }

        $this->checkTables();
        $this->showPostInstall();

        $this->newLine();
        $this->info('API Debugger installed successfully!');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->components->task('Publishing config', function () {
            $this->callSilently('vendor:publish', ['--tag' => 'api-debugger-config']);
            return true;
        });
    }

    protected function runMigrations(): void
    {
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate');
            return true;
        });
    }

    protected function checkTables(): void
    {
        $this->components->task('Checking api_logs table', function () {
            return Schema::hasTable('api_logs');
        });

        $this->components->task('Checking api_debug_sessions table', function () {
            return Schema::hasTable('api_debug_sessions');
        });
    }

    protected function showPostInstall(): void
    {
        $this->newLine();
        $this->components->info('Post-install:');
        $this->newLine();

        $this->line('  1. Register the middleware on routes you want to log:');
        $this->newLine();
        $this->line("     // In routes/api.php or route groups:");
        $this->line("     Route::middleware('api-debugger')->group(function () {");
        $this->line("         // your API routes...");
        $this->line("     });");
        $this->newLine();

        $this->line('  2. Visit /api-debugger to see the dashboard.');
        $this->newLine();

        $this->line('  3. Manage debug sessions:');
        $this->line('     php artisan api-debugger:enable');
        $this->line('     php artisan api-debugger:disable');
        $this->line('     php artisan api-debugger:status');
        $this->newLine();

        $this->line('  4. (Optional) Schedule log cleanup in your Kernel:');
        $this->line("     // Auto-scheduled by the package based on config.");
    }
}
