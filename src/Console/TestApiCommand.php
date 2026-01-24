<?php

namespace IndieSystems\ApiDebugger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestApiCommand extends Command
{
    protected $signature = 'api-debugger:test
                            {--all : Run all test endpoints}
                            {--endpoint= : Run specific endpoint (ping, echo, submit, update, delete, slow, error, validate, large)}
                            {--count=1 : Number of requests to make}
                            {--token= : Debug session token to include in requests (X-Debug-Token header)}';

    protected $description = 'Make test API requests to generate debug logs';

    protected array $endpoints = [
        'ping' => ['method' => 'GET', 'path' => '/api/test/ping'],
        'echo' => ['method' => 'GET', 'path' => '/api/test/echo', 'query' => ['foo' => 'bar', 'test' => 'value']],
        'submit' => ['method' => 'POST', 'path' => '/api/test/submit', 'body' => ['name' => 'Test User', 'email' => 'test@example.com']],
        'update' => ['method' => 'PUT', 'path' => '/api/test/update/123', 'body' => ['name' => 'Updated Name']],
        'delete' => ['method' => 'DELETE', 'path' => '/api/test/delete/456'],
        'slow' => ['method' => 'GET', 'path' => '/api/test/slow'],
        'error' => ['method' => 'GET', 'path' => '/api/test/error'],
        'validate' => ['method' => 'POST', 'path' => '/api/test/validate', 'body' => ['email' => 'invalid', 'name' => 'ab']],
        'large' => ['method' => 'GET', 'path' => '/api/test/large'],
    ];

    public function handle(): int
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $count = (int) $this->option('count');
        $token = $this->option('token');

        $this->info("Base URL: {$baseUrl}");
        if ($token) {
            $this->info("Debug Token: {$token}");
        }
        $this->newLine();

        if ($this->option('endpoint')) {
            $endpoint = $this->option('endpoint');
            if (!isset($this->endpoints[$endpoint])) {
                $this->error("Unknown endpoint: {$endpoint}");
                $this->line("Available: " . implode(', ', array_keys($this->endpoints)));
                return self::FAILURE;
            }
            $this->runEndpoint($baseUrl, $endpoint, $this->endpoints[$endpoint], $count, $token);
        } elseif ($this->option('all')) {
            foreach ($this->endpoints as $name => $config) {
                $this->runEndpoint($baseUrl, $name, $config, $count, $token);
            }
        } else {
            $this->info('Available test endpoints:');
            $this->newLine();

            foreach ($this->endpoints as $name => $config) {
                $this->line(sprintf(
                    "  <comment>%-10s</comment> %s %s",
                    $name,
                    $config['method'],
                    $config['path']
                ));
            }

            $this->newLine();
            $this->line('Usage:');
            $this->line('  php artisan api-debugger:test --all              # Run all endpoints');
            $this->line('  php artisan api-debugger:test --endpoint=ping    # Run specific endpoint');
            $this->line('  php artisan api-debugger:test --all --count=5    # Run all endpoints 5 times');
        }

        return self::SUCCESS;
    }

    protected function runEndpoint(string $baseUrl, string $name, array $config, int $count, ?string $token = null): void
    {
        $url = $baseUrl . $config['path'];
        $method = strtoupper($config['method']);

        $this->line("<info>{$method}</info> {$config['path']}");

        for ($i = 1; $i <= $count; $i++) {
            $startTime = microtime(true);

            try {
                $request = Http::timeout(30)->acceptJson();

                // Add debug token header if provided
                if ($token) {
                    $request = $request->withHeaders(['X-Debug-Token' => $token]);
                }

                $response = match ($method) {
                    'GET' => $request->get($url, $config['query'] ?? []),
                    'POST' => $request->post($url, $config['body'] ?? []),
                    'PUT' => $request->put($url, $config['body'] ?? []),
                    'PATCH' => $request->patch($url, $config['body'] ?? []),
                    'DELETE' => $request->delete($url),
                    default => throw new \Exception("Unknown method: {$method}"),
                };

                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $status = $response->status();

                $statusColor = match (true) {
                    $status >= 500 => 'red',
                    $status >= 400 => 'yellow',
                    $status >= 200 && $status < 300 => 'green',
                    default => 'white',
                };

                if ($count > 1) {
                    $this->line(sprintf(
                        "  [%d/%d] <fg=%s>%d</> in %sms",
                        $i,
                        $count,
                        $statusColor,
                        $status,
                        $duration
                    ));
                } else {
                    $this->line(sprintf(
                        "  <fg=%s>%d</> in %sms",
                        $statusColor,
                        $status,
                        $duration
                    ));
                }

            } catch (\Exception $e) {
                $this->error("  Error: " . $e->getMessage());
            }
        }

        $this->newLine();
    }
}
