<?php

namespace IndieSystems\ApiDebugger\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use IndieSystems\ApiDebugger\Models\ApiDebugSession;
use IndieSystems\ApiDebugger\Models\ApiLog;
use IndieSystems\ApiDebugger\Services\ApiDebuggerService;

class ApiDebuggerController extends Controller
{
    protected ApiDebuggerService $debugger;

    public function __construct(ApiDebuggerService $debugger)
    {
        $this->debugger = $debugger;
    }

    /**
     * Dashboard / Index page.
     */
    public function index(Request $request)
    {
        $sessions = ApiDebugSession::active()
            ->with('user', 'createdBy')
            ->withCount('logs')
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total_logs' => ApiLog::count(),
            'logs_today' => ApiLog::whereDate('created_at', today())->count(),
            'active_sessions' => $sessions->count(),
            'avg_response_time' => ApiLog::whereDate('created_at', today())->avg('duration_ms'),
            'error_rate' => $this->calculateErrorRate(),
        ];

        return view('api-debugger::index', compact('sessions', 'stats'));
    }

    /**
     * List logs with filtering.
     */
    public function logs(Request $request)
    {
        $query = ApiLog::with('session', 'user')
            ->orderByDesc('requested_at');

        // Filters
        if ($request->filled('session_id')) {
            $query->where('api_debug_session_id', $request->session_id);
        }

        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->method));
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'success') {
                $query->successful();
            } elseif ($status === 'error') {
                $query->failed();
            } elseif (is_numeric($status)) {
                $query->where('status_code', $status);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $perPage = config('api-debugger.ui.per_page', 25);
        $logs = $query->paginate($perPage)->withQueryString();

        // For AJAX requests, return just the table partial
        if ($request->ajax()) {
            return view('api-debugger::partials.logs-table', compact('logs'));
        }

        return view('api-debugger::logs', compact('logs'));
    }

    /**
     * Show single log detail.
     */
    public function show(ApiLog $log)
    {
        $log->load('session', 'user');

        return view('api-debugger::show', compact('log'));
    }

    /**
     * Get log detail as JSON (for AJAX modal).
     */
    public function showJson(ApiLog $log)
    {
        return response()->json([
            'id' => $log->id,
            'method' => $log->method,
            'method_color' => $log->method_color,
            'url' => $log->url,
            'full_url' => $log->full_url,
            'route_name' => $log->route_name,
            'status_code' => $log->status_code,
            'status_color' => $log->status_color,
            'duration' => $log->formatted_duration,
            'request_size' => $log->formatted_request_size,
            'response_size' => $log->formatted_response_size,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name ?? $log->user->email,
            ] : null,
            'tenant_id' => $log->tenant_id,
            'request_headers' => $log->getRequestHeadersForDisplay(),
            'request_query' => $log->request_query,
            'request_body' => $log->parsed_request_body,
            'request_content_type' => $log->request_content_type,
            'response_headers' => $log->getResponseHeadersForDisplay(),
            'response_body' => $log->parsed_response_body,
            'response_content_type' => $log->response_content_type,
            'has_exception' => $log->has_exception,
            'exception_class' => $log->exception_class,
            'exception_message' => $log->exception_message,
            'exception_trace' => $log->exception_trace,
            'requested_at' => $log->requested_at?->format(config('api-debugger.ui.date_format')),
            'responded_at' => $log->responded_at?->format(config('api-debugger.ui.date_format')),
            'memory_peak_mb' => $log->memory_peak_mb ? round($log->memory_peak_mb, 2) . ' MB' : null,
        ]);
    }

    /**
     * Manage debug sessions.
     */
    public function sessions()
    {
        $activeSessions = ApiDebugSession::active()
            ->with('user', 'createdBy')
            ->withCount('logs')
            ->orderByDesc('created_at')
            ->get();

        $recentSessions = ApiDebugSession::where('active', false)
            ->with('user', 'createdBy')
            ->withCount('logs')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return view('api-debugger::sessions', compact('activeSessions', 'recentSessions'));
    }

    /**
     * Create a new debug session.
     */
    public function createSession(Request $request)
    {
        $request->validate([
            'type' => 'required|in:all,tenant,user',
            'duration' => 'nullable|integer|min:1|max:' . config('api-debugger.session.max_duration', 120),
            'label' => 'nullable|string|max:255',
            'target_id' => 'required_unless:type,all|nullable|string|max:255',
        ]);

        $duration = $request->duration ?? config('api-debugger.session.default_duration', 30);
        $createdBy = auth()->id();

        if ($request->type === 'all') {
            $session = $this->debugger->enableGlobal($duration, $createdBy);
        } elseif ($request->type === 'tenant') {
            $session = $this->debugger->enableForTenant($request->target_id, $duration, $createdBy);
        } else {
            $session = $this->debugger->enableForUser($request->target_id, $duration, $createdBy);
        }

        if ($request->filled('label')) {
            $session->update(['label' => $request->label]);
        }

        return redirect()->route('api-debugger.sessions')
            ->with('success', 'Debug session created. Expires at ' . $session->expires_at->format('H:i:s'));
    }

    /**
     * Extend a session's duration.
     */
    public function extendSession(Request $request, ApiDebugSession $session)
    {
        $request->validate([
            'duration' => 'required|integer|min:1|max:' . config('api-debugger.session.max_duration', 120),
        ]);

        $session->extend($request->duration);

        return redirect()->back()
            ->with('success', 'Session extended. New expiry: ' . $session->expires_at->format('H:i:s'));
    }

    /**
     * Stop a debug session.
     */
    public function stopSession(ApiDebugSession $session)
    {
        $this->debugger->disable($session);

        return redirect()->back()->with('success', 'Debug session stopped');
    }

    /**
     * Delete a session and its logs.
     */
    public function deleteSession(ApiDebugSession $session)
    {
        $logCount = $session->logs()->count();
        $session->logs()->delete();
        $session->delete();

        return redirect()->route('api-debugger.sessions')
            ->with('success', "Session deleted with {$logCount} log(s)");
    }

    /**
     * Delete a single log.
     */
    public function deleteLog(ApiLog $log)
    {
        $log->delete();

        return redirect()->back()->with('success', 'Log deleted');
    }

    /**
     * Clear all logs (admin action).
     */
    public function clearLogs(Request $request)
    {
        $sessionId = $request->session_id;

        if ($sessionId) {
            $count = ApiLog::where('api_debug_session_id', $sessionId)->delete();
        } else {
            $count = ApiLog::truncate();
        }

        return redirect()->back()->with('success', "Cleared {$count} log(s)");
    }

    /**
     * List API routes.
     */
    public function routes(Request $request)
    {
        $apiPrefixes = config('api-debugger.routes.api_prefixes', ['api', 'api/*', 'tenant/*/api']);
        $showAllRoutes = $request->boolean('all');

        $routes = collect(app('router')->getRoutes())
            ->filter(function ($route) use ($apiPrefixes, $showAllRoutes) {
                if ($showAllRoutes) {
                    return true;
                }

                $uri = $route->uri();
                $middleware = $route->middleware();

                // Check if route matches any API prefix pattern
                foreach ($apiPrefixes as $prefix) {
                    if (str_contains($prefix, '*')) {
                        $pattern = str_replace('*', '.*', $prefix);
                        if (preg_match('#^' . $pattern . '#', $uri)) {
                            return true;
                        }
                    } elseif (str_starts_with($uri, $prefix . '/') || $uri === $prefix) {
                        return true;
                    }
                }

                // Check middleware
                return in_array('api', $middleware) ||
                       collect($middleware)->contains(fn($m) => str_contains($m, 'api'));
            })
            ->map(function ($route) {
                return [
                    'methods' => $route->methods(),
                    'uri' => $route->uri(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->middleware(),
                    'parameters' => $this->extractRouteParameters($route->uri()),
                ];
            })
            ->sortBy('uri')
            ->values();

        return view('api-debugger::routes', compact('routes'));
    }

    /**
     * Extract parameters from route URI.
     */
    protected function extractRouteParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Calculate error rate for today.
     */
    protected function calculateErrorRate(): float
    {
        $today = ApiLog::whereDate('created_at', today());
        $total = $today->count();

        if ($total === 0) {
            return 0;
        }

        $errors = (clone $today)->where('status_code', '>=', 400)->count();

        return round(($errors / $total) * 100, 2);
    }
}
