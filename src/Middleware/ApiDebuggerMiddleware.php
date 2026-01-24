<?php

namespace IndieSystems\ApiDebugger\Middleware;

use Closure;
use Illuminate\Http\Request;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;
use IndieSystems\ApiDebugger\Jobs\StoreApiLog;
use Symfony\Component\HttpFoundation\Response;

class ApiDebuggerMiddleware
{
    protected ApiDebuggerService $debugger;

    public function __construct(ApiDebuggerService $debugger)
    {
        $this->debugger = $debugger;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Quick bail-out if disabled globally
        if (!config('api-debugger.enabled', false)) {
            return $next($request);
        }

        // Check if route should be excluded
        if ($this->debugger->shouldExcludeRoute($request)) {
            return $next($request);
        }

        // Check if there's an active debug session for this request context
        $session = $this->debugger->getActiveSession($request);

        if (!$session) {
            return $next($request);
        }

        // Capture request data before processing
        $requestData = $this->debugger->captureRequest($request);

        $exception = null;

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $exception = $e;
            throw $e;
        } finally {
            // Capture response (or exception)
            $this->logRequest($session, $requestData, $response ?? null, $exception);
        }

        return $response;
    }

    protected function logRequest($session, array $requestData, ?Response $response, ?\Throwable $exception): void
    {
        $responseData = [];
        $exceptionData = [];

        if ($response) {
            $responseData = $this->debugger->captureResponse($response, $requestData);
        }

        if ($exception) {
            $exceptionData = $this->debugger->captureException($exception);
            // Set a status code for exceptions if no response
            if (empty($responseData)) {
                $responseData = [
                    'status_code' => 500,
                    'response_body' => null,
                    'response_headers' => [],
                    'responded_at' => now(),
                ];
            }
        }

        $driver = config('api-debugger.driver', 'sync');

        if ($driver === 'queue') {
            StoreApiLog::dispatch($session->id, $requestData, $responseData, $exceptionData)
                ->onConnection(config('api-debugger.queue.connection', 'redis'))
                ->onQueue(config('api-debugger.queue.name', 'api-debugger'));
        } else {
            $this->debugger->storeLog($session, $requestData, $responseData, $exceptionData);
        }
    }
}
