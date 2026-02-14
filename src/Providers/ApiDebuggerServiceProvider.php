<?php

namespace IndieSystems\ApiDebugger\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use IndieSystems\ApiDebugger\Middleware\ApiDebuggerMiddleware;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;
use IndieSystems\ApiDebugger\Services\TenancyDetector;
use IndieSystems\ApiDebugger\Console\EnableDebugCommand;
use IndieSystems\ApiDebugger\Console\DisableDebugCommand;
use IndieSystems\ApiDebugger\Console\PruneLogsCommand;
use IndieSystems\ApiDebugger\Console\StatusCommand;
use IndieSystems\ApiDebugger\Console\TestApiCommand;

class ApiDebuggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/api-debugger.php', 'api-debugger');

        // Register services
        $this->app->singleton(TenancyDetector::class, function ($app) {
            $customDetector = config('api-debugger.tenancy.detector');

            if ($customDetector && class_exists($customDetector)) {
                return $app->make($customDetector);
            }

            return new TenancyDetector();
        });

        $this->app->singleton(ApiDebuggerService::class, function ($app) {
            return new ApiDebuggerService($app->make(TenancyDetector::class));
        });

        // Alias for convenience
        $this->app->alias(ApiDebuggerService::class, 'api.debugger');
    }

    public function boot(Router $router): void
    {
        // Register middleware alias
        $router->aliasMiddleware('api-debugger', ApiDebuggerMiddleware::class);

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../views', 'api-debugger');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register routes
        $this->registerRoutes();

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnableDebugCommand::class,
                DisableDebugCommand::class,
                PruneLogsCommand::class,
                StatusCommand::class,
                TestApiCommand::class,
                \IndieSystems\ApiDebugger\Console\InstallCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__ . '/../config/api-debugger.php' => config_path('api-debugger.php'),
            ], 'api-debugger-config');

            // Publish views
            $this->publishes([
                __DIR__ . '/../views' => resource_path('views/vendor/api-debugger'),
            ], 'api-debugger-views');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'api-debugger-migrations');
        }

        // Schedule cleanup tasks
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            // Deactivate expired sessions every minute
            $schedule->call(function () {
                app(ApiDebuggerService::class)->deactivateExpiredSessions();
            })->everyMinute()->name('api-debugger:deactivate-expired');

            // Prune old logs based on config
            $cleanupInterval = config('api-debugger.retention.cleanup_interval', 60);
            $schedule->command('api-debugger:prune')
                ->cron("*/{$cleanupInterval} * * * *")
                ->name('api-debugger:prune');
        });
    }

    protected function registerRoutes(): void
    {
        $prefix = config('api-debugger.ui.prefix', 'api-debugger');
        $middleware = config('api-debugger.ui.middleware', ['web', 'auth']);

        // Add authorization middleware if gate is configured
        $gate = config('api-debugger.authorization.gate');
        if ($gate) {
            $middleware[] = "can:{$gate}";
        }

        $this->app['router']->group([
            'prefix' => $prefix,
            'middleware' => $middleware,
            'as' => '',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }
}
