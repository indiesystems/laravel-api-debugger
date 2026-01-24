<?php

namespace IndieSystems\ApiDebugger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use IndieSystems\AdminLteUiComponents\Traits\Formable;

class ApiLog extends Model
{
    use Formable;

    protected $fillable = [
        'api_debug_session_id',
        'tenant_id',
        'user_id',
        'request_id',
        'method',
        'url',
        'full_url',
        'route_name',
        'route_action',
        'request_headers',
        'request_query',
        'request_body',
        'request_content_type',
        'request_size',
        'ip_address',
        'user_agent',
        'status_code',
        'response_headers',
        'response_body',
        'response_content_type',
        'response_size',
        'duration_ms',
        'memory_peak_mb',
        'exception_class',
        'exception_message',
        'exception_trace',
        'requested_at',
        'responded_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_query' => 'array',
        'response_headers' => 'array',
        'duration_ms' => 'float',
        'memory_peak_mb' => 'float',
        'request_size' => 'integer',
        'response_size' => 'integer',
        'status_code' => 'integer',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public $formable = [
        'method',
        'url',
        'status_code',
        'duration_ms',
        'ip_address',
        'requested_at',
    ];

    protected $fillableFormFields = [
        ['type' => 'text', 'name' => 'method', 'label' => 'Method'],
        ['type' => 'text', 'name' => 'url', 'label' => 'URL'],
        ['type' => 'text', 'name' => 'status_code', 'label' => 'Status'],
        ['type' => 'text', 'name' => 'duration_ms', 'label' => 'Duration'],
        ['type' => 'text', 'name' => 'ip_address', 'label' => 'IP'],
        ['type' => 'text', 'name' => 'requested_at', 'label' => 'Time'],
    ];

    public function getConnectionName(): ?string
    {
        return config('api-debugger.connection') ?? parent::getConnectionName();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function session(): BelongsTo
    {
        return $this->belongsTo(ApiDebugSession::class, 'api_debug_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus(Builder $query, int $status): Builder
    {
        return $query->where('status_code', $status);
    }

    public function scopeWithMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', strtoupper($method));
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [200, 299]);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 400);
    }

    public function scopeSlowRequests(Builder $query, float $thresholdMs = 1000): Builder
    {
        return $query->where('duration_ms', '>=', $thresholdMs);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getMethodColorAttribute(): string
    {
        return match ($this->method) {
            'GET' => 'info',
            'POST' => 'success',
            'PUT', 'PATCH' => 'warning',
            'DELETE' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match (true) {
            $this->status_code >= 500 => 'danger',
            $this->status_code >= 400 => 'warning',
            $this->status_code >= 300 => 'info',
            $this->status_code >= 200 => 'success',
            default => 'secondary',
        };
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_ms === null) {
            return '-';
        }

        if ($this->duration_ms >= 1000) {
            return number_format($this->duration_ms / 1000, 2) . 's';
        }

        return number_format($this->duration_ms, 2) . 'ms';
    }

    public function getFormattedRequestSizeAttribute(): string
    {
        return $this->formatBytes($this->request_size);
    }

    public function getFormattedResponseSizeAttribute(): string
    {
        return $this->formatBytes($this->response_size);
    }

    public function getHasExceptionAttribute(): bool
    {
        return !empty($this->exception_class);
    }

    public function getIsJsonRequestAttribute(): bool
    {
        return str_contains($this->request_content_type ?? '', 'json');
    }

    public function getIsJsonResponseAttribute(): bool
    {
        return str_contains($this->response_content_type ?? '', 'json');
    }

    public function getParsedRequestBodyAttribute(): mixed
    {
        if ($this->is_json_request && $this->request_body) {
            return json_decode($this->request_body, true) ?? $this->request_body;
        }

        return $this->request_body;
    }

    public function getParsedResponseBodyAttribute(): mixed
    {
        if ($this->is_json_response && $this->response_body) {
            return json_decode($this->response_body, true) ?? $this->response_body;
        }

        return $this->response_body;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getRequestHeadersForDisplay(): array
    {
        $headers = $this->request_headers ?? [];
        $redacted = array_map('strtolower', config('api-debugger.redact.headers', []));

        return collect($headers)->mapWithKeys(function ($value, $key) use ($redacted) {
            if (in_array(strtolower($key), $redacted)) {
                return [$key => config('api-debugger.redact.replacement', '[REDACTED]')];
            }
            return [$key => is_array($value) ? implode(', ', $value) : $value];
        })->toArray();
    }

    public function getResponseHeadersForDisplay(): array
    {
        $headers = $this->response_headers ?? [];
        $redacted = array_map('strtolower', config('api-debugger.redact.headers', []));

        return collect($headers)->mapWithKeys(function ($value, $key) use ($redacted) {
            if (in_array(strtolower($key), $redacted)) {
                return [$key => config('api-debugger.redact.replacement', '[REDACTED]')];
            }
            return [$key => is_array($value) ? implode(', ', $value) : $value];
        })->toArray();
    }
}
