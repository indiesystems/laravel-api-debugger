<?php

namespace IndieSystems\ApiDebugger\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use IndieSystems\ApiDebugger\Models\ApiDebugSession;
use IndieSystems\ApiDebugger\Models\ApiLog;

class StoreApiLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public int $sessionId,
        public array $requestData,
        public array $responseData,
        public array $exceptionData = []
    ) {}

    public function handle(): void
    {
        $session = ApiDebugSession::find($this->sessionId);

        if (!$session) {
            return; // Session was deleted
        }

        // Remove internal tracking data
        $requestData = $this->requestData;
        unset($requestData['_start_time'], $requestData['_memory_start']);

        ApiLog::create(array_merge(
            ['api_debug_session_id' => $this->sessionId],
            $requestData,
            $this->responseData,
            $this->exceptionData
        ));
    }

    public function tags(): array
    {
        return ['api-debugger', 'session:' . $this->sessionId];
    }
}
