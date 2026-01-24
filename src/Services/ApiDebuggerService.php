<?php

namespace IndieSystems\ApiDebugger\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use IndieSystems\ApiDebugger\Models\ApiDebugSession;
use IndieSystems\ApiDebugger\Models\ApiLog;

class ApiDebuggerService
{
    protected TenancyDetector $tenancyDetector;

    public function __construct(?TenancyDetector $tenancyDetector = null)
    {
        $customDetector = config('api-debugger.tenancy.detector');

        if ($customDetector && class_exists($customDetector)) {
            $this->tenancyDetector = app($customDetector);
        } else {
            $this->tenancyDetector = $tenancyDetector ?? new TenancyDetector();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    */

    /**
     * Enable global debugging (token-based only).
     */
    public function enableGlobal(?int $minutes = null, ?int $createdBy = null): ApiDebugSession
    {
        $minutes = $this->clampDuration($minutes);

        return ApiDebugSession::create([
            'tenant_id' => null,
            'user_id' => null,
            'active' => true,
            'expires_at' => now()->addMinutes($minutes),
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Enable debugging for a specific tenant.
     */
    public function enableForTenant(string $tenantId, ?int $minutes = null, ?int $createdBy = null): ApiDebugSession
    {
        $minutes = $this->clampDuration($minutes);

        return ApiDebugSession::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => null],
            [
                'active' => true,
                'expires_at' => now()->addMinutes($minutes),
                'created_by' => $createdBy,
            ]
        );
    }

    /**
     * Enable debugging for a specific user.
     */
    public function enableForUser(string|int $userId, ?int $minutes = null, ?int $createdBy = null): ApiDebugSession
    {
        $minutes = $this->clampDuration($minutes);

        return ApiDebugSession::updateOrCreate(
            ['user_id' => $userId, 'tenant_id' => null],
            [
                'active' => true,
                'expires_at' => now()->addMinutes($minutes),
                'created_by' => $createdBy,
            ]
        );
    }

    /**
     * Enable debugging for a specific tenant + user combination.
     */
    public function enableForTenantUser(string $tenantId, string|int $userId, ?int $minutes = null, ?int $createdBy = null): ApiDebugSession
    {
        $minutes = $this->clampDuration($minutes);

        return ApiDebugSession::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            [
                'active' => true,
                'expires_at' => now()->addMinutes($minutes),
                'created_by' => $createdBy,
            ]
        );
    }

    /**
     * Disable a specific debug session.
     */
    public function disable(ApiDebugSession $session): void
    {
        $session->stop();
        $this->clearSessionCache($session);
    }

    /**
     * Disable all sessions for a tenant.
     */
    public function disableForTenant(string $tenantId): int
    {
        $sessions = ApiDebugSession::forTenant($tenantId)->active()->get();

        foreach ($sessions as $session) {
            $this->disable($session);
        }

        return $sessions->count();
    }

    /**
     * Disable all sessions for a user.
     */
    public function disableForUser(string|int $userId): int
    {
        $sessions = ApiDebugSession::forUser($userId)->active()->get();

        foreach ($sessions as $session) {
            $this->disable($session);
        }

        return $sessions->count();
    }

    /**
     * Get active session for the current request context.
     *
     * Session matching priority:
     * 1. X-Debug-Token header (if provided)
     * 2. Global session (logs all requests)
     * 3. Tenant + User combination
     * 4. User only (if authenticated)
     * 5. Tenant only
     */
    public function getActiveSession(Request $request): ?ApiDebugSession
    {
        // First, check for explicit debug token (header or query param)
        $debugToken = $request->header('X-Debug-Token') ?? $request->query('_debug_token');
        if ($debugToken) {
            return $this->getSessionByToken($debugToken);
        }

        // Check for global session (logs all requests)
        $globalSession = $this->getGlobalSession();
        if ($globalSession) {
            return $globalSession;
        }

        if (!config('api-debugger.tenancy.enabled', true)) {
            $tenantId = null;
        } else {
            $tenantId = $this->tenancyDetector->detect($request);
        }

        $userId = $request->user()?->id;

        // If we have neither tenant nor user, no session can match
        if ($tenantId === null && $userId === null) {
            return null;
        }

        $cacheKey = $this->getSessionCacheKey($tenantId, $userId);
        $cacheTtl = config('api-debugger.session.cache_ttl', 60);

        return cache()->remember($cacheKey, $cacheTtl, function () use ($tenantId, $userId) {
            return ApiDebugSession::query()
                ->active()
                ->where(function ($query) use ($tenantId, $userId) {
                    // Match tenant-specific session (only if tenant is set)
                    if ($tenantId !== null) {
                        $query->where(function ($q) use ($tenantId) {
                            $q->where('tenant_id', $tenantId)->whereNull('user_id');
                        });
                    }
                    // Or user-specific session (only if user is authenticated)
                    if ($userId !== null) {
                        $query->orWhere(function ($q) use ($userId) {
                            $q->where('user_id', $userId)->whereNull('tenant_id');
                        });
                    }
                    // Or tenant+user specific session (only if both are set)
                    if ($tenantId !== null && $userId !== null) {
                        $query->orWhere(function ($q) use ($tenantId, $userId) {
                            $q->where('tenant_id', $tenantId)->where('user_id', $userId);
                        });
                    }
                })
                ->orderByDesc('created_at')
                ->first();
        });
    }

    /**
     * Get active global session (logs all requests).
     */
    public function getGlobalSession(): ?ApiDebugSession
    {
        $cacheKey = 'api_debugger.session.global';
        $cacheTtl = config('api-debugger.session.cache_ttl', 60);

        return cache()->remember($cacheKey, $cacheTtl, function () {
            return ApiDebugSession::query()
                ->active()
                ->whereNull('tenant_id')
                ->whereNull('user_id')
                ->orderByDesc('created_at')
                ->first();
        });
    }

    /**
     * Get active session by debug token.
     */
    public function getSessionByToken(string $token): ?ApiDebugSession
    {
        $cacheKey = 'api_debugger.session.token.' . $token;
        $cacheTtl = config('api-debugger.session.cache_ttl', 60);

        return cache()->remember($cacheKey, $cacheTtl, function () use ($token) {
            return ApiDebugSession::where('token', $token)
                ->active()
                ->first();
        });
    }

    /**
     * Get all active sessions.
     */
    public function getActiveSessions()
    {
        return ApiDebugSession::active()
            ->with('user', 'createdBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Request Capture
    |--------------------------------------------------------------------------
    */

    /**
     * Capture request data for logging.
     */
    public function captureRequest(Request $request): array
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        return [
            'request_id' => (string) Str::uuid(),
            'method' => $request->method(),
            'url' => $request->path(),
            'full_url' => $request->fullUrl(),
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
            'request_headers' => $this->redactHeaders($request->headers->all()),
            'request_query' => $request->query(),
            'request_body' => $this->captureBody($request->getContent(), $request->header('Content-Type')),
            'request_content_type' => $request->header('Content-Type'),
            'request_size' => strlen($request->getContent() ?? ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tenant_id' => config('api-debugger.tenancy.enabled') ? $this->tenancyDetector->detect($request) : null,
            'user_id' => $request->user()?->id,
            'requested_at' => now(),
            '_start_time' => $startTime,
            '_memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * Capture response data for logging.
     */
    public function captureResponse($response, array $requestData): array
    {
        $content = $response->getContent();
        $endTime = microtime(true);
        $startTime = $requestData['_start_time'] ?? $endTime;

        return [
            'status_code' => $response->getStatusCode(),
            'response_headers' => $this->redactHeaders($response->headers->all()),
            'response_body' => $this->captureBody($content, $response->headers->get('Content-Type')),
            'response_content_type' => $response->headers->get('Content-Type'),
            'response_size' => strlen($content ?? ''),
            'duration_ms' => ($endTime - $startTime) * 1000,
            'memory_peak_mb' => memory_get_peak_usage(true) / 1024 / 1024,
            'responded_at' => now(),
        ];
    }

    /**
     * Capture exception data for logging.
     */
    public function captureException(\Throwable $exception): array
    {
        return [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_trace' => $exception->getTraceAsString(),
        ];
    }

    /**
     * Store the captured log.
     */
    public function storeLog(ApiDebugSession $session, array $requestData, array $responseData, array $exceptionData = []): ApiLog
    {
        // Remove internal tracking data
        unset($requestData['_start_time'], $requestData['_memory_start']);

        return ApiLog::create(array_merge(
            ['api_debug_session_id' => $session->id],
            $requestData,
            $responseData,
            $exceptionData
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Route Filtering
    |--------------------------------------------------------------------------
    */

    /**
     * Check if a route should be excluded from logging.
     */
    public function shouldExcludeRoute(Request $request): bool
    {
        $path = $request->path();
        $excludePatterns = config('api-debugger.routes.exclude', []);
        $includePatterns = config('api-debugger.routes.include', []);

        // Check exclusions first
        foreach ($excludePatterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        // If include patterns are specified, only log matching routes
        if (!empty($includePatterns)) {
            foreach ($includePatterns as $pattern) {
                if ($this->matchesPattern($path, $pattern)) {
                    return false;
                }
            }
            return true; // No include pattern matched
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Cleanup
    |--------------------------------------------------------------------------
    */

    /**
     * Deactivate expired sessions.
     */
    public function deactivateExpiredSessions(): int
    {
        return ApiDebugSession::expired()
            ->where('active', true)
            ->update(['active' => false]);
    }

    /**
     * Delete old logs based on retention policy.
     */
    public function pruneOldLogs(): int
    {
        $hours = config('api-debugger.retention.hours', 24);

        return ApiLog::where('created_at', '<', now()->subHours($hours))->delete();
    }

    /**
     * Delete logs for inactive sessions.
     */
    public function pruneInactiveSessions(): int
    {
        $inactiveSessionIds = ApiDebugSession::where('active', false)
            ->where('updated_at', '<', now()->subHours(1))
            ->pluck('id');

        if ($inactiveSessionIds->isEmpty()) {
            return 0;
        }

        $deletedLogs = ApiLog::whereIn('api_debug_session_id', $inactiveSessionIds)->delete();

        ApiDebugSession::whereIn('id', $inactiveSessionIds)->delete();

        return $deletedLogs;
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    protected function clampDuration(?int $minutes): int
    {
        $default = config('api-debugger.session.default_duration', 30);
        $max = config('api-debugger.session.max_duration', 120);

        return min($minutes ?? $default, $max);
    }

    protected function getSessionCacheKey(?string $tenantId, ?int $userId): string
    {
        return 'api_debugger.session.' . ($tenantId ?? 'null') . '.' . ($userId ?? 'null');
    }

    protected function clearSessionCache(ApiDebugSession $session): void
    {
        // Clear specific session cache
        $cacheKey = $this->getSessionCacheKey($session->tenant_id, $session->user_id);
        cache()->forget($cacheKey);

        // Clear global session cache if this is a global session
        if ($session->tenant_id === null && $session->user_id === null) {
            cache()->forget('api_debugger.session.global');
        }

        // Clear token cache if session has a token
        if ($session->token) {
            cache()->forget('api_debugger.session.token.' . $session->token);
        }
    }

    protected function redactHeaders(array $headers): array
    {
        $redactedKeys = array_map('strtolower', config('api-debugger.redact.headers', []));
        $replacement = config('api-debugger.redact.replacement', '[REDACTED]');

        return collect($headers)->mapWithKeys(function ($value, $key) use ($redactedKeys, $replacement) {
            if (in_array(strtolower($key), $redactedKeys)) {
                return [$key => [$replacement]];
            }
            return [$key => $value];
        })->toArray();
    }

    protected function captureBody(?string $content, ?string $contentType): ?string
    {
        if (empty($content)) {
            return null;
        }

        if (!config('api-debugger.body.store', true)) {
            return '[Body storage disabled]';
        }

        $maxSize = config('api-debugger.body.max_size');

        if ($maxSize !== null && strlen($content) > $maxSize) {
            return substr($content, 0, $maxSize) . "\n\n[TRUNCATED - Original size: " . strlen($content) . " bytes]";
        }

        // Redact sensitive fields in JSON content
        if ($contentType && str_contains($contentType, 'json')) {
            $content = $this->redactJsonFields($content);
        }

        return $content;
    }

    protected function redactJsonFields(string $json): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $json;
        }

        $redactedFields = config('api-debugger.redact.fields', []);
        $replacement = config('api-debugger.redact.replacement', '[REDACTED]');

        $data = $this->redactArrayFields($data, $redactedFields, $replacement);

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function redactArrayFields(array $data, array $fields, string $replacement): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $fields))) {
                $data[$key] = $replacement;
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArrayFields($value, $fields, $replacement);
            }
        }

        return $data;
    }

    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '/'],
            ['.*', '\/'],
            $pattern
        );

        return (bool) preg_match('/^' . $regex . '$/i', $path);
    }
}
