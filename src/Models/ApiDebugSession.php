<?php

namespace IndieSystems\ApiDebugger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ApiDebugSession extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'label',
        'token',
        'active',
        'expires_at',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApiDebugSession $session) {
            if (empty($session->token)) {
                $session->token = Str::random(32);
            }
        });
    }

    protected $casts = [
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        // If a specific connection is configured, use it
        if ($connection = config('api-debugger.connection')) {
            return $connection;
        }

        // If in tenant context (stancl/tenancy), use central connection
        if (function_exists('tenancy') && tenancy()->initialized) {
            return config('tenancy.database.central_connection', 'mysql');
        }

        return parent::getConnectionName();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class, 'api_debug_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'), 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->active && !$this->isExpired();
    }

    public function remainingMinutes(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->expires_at, false);
    }

    public function extend(int $minutes): self
    {
        $maxDuration = config('api-debugger.session.max_duration', 120);
        $newExpiry = now()->addMinutes(min($minutes, $maxDuration));

        $this->update([
            'expires_at' => $newExpiry,
            'active' => true,
        ]);

        return $this;
    }

    public function stop(): self
    {
        $this->update(['active' => false]);
        return $this;
    }

    public function getTargetLabel(): string
    {
        if ($this->user_id) {
            $label = 'User: ';
            if ($this->user) {
                $label .= $this->user->name ?? $this->user->email ?? "#{$this->user_id}";
            } else {
                $label .= "#{$this->user_id}";
            }
            return $label;
        }

        if ($this->tenant_id) {
            return 'Tenant: ' . $this->tenant_id;
        }

        return 'All Requests';
    }
}
