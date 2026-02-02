<?php

namespace IndieSystems\ApiDebugger\Services;

use Illuminate\Http\Request;

class TenancyDetector
{
    /**
     * Detect the current tenant ID from the request.
     *
     * Checks common tenancy packages. If no package has initialized
     * a tenant, returns null (not in tenant context).
     */
    public function detect(Request $request): ?string
    {
        // Try stancl/tenancy
        if (function_exists('tenant') && tenant()) {
            $tenant = tenant();
            return $tenant->id ?? $tenant->getTenantKey() ?? null;
        }

        // Try spatie/laravel-multitenancy
        if (class_exists(\Spatie\Multitenancy\Models\Tenant::class)) {
            $tenant = \Spatie\Multitenancy\Models\Tenant::current();
            if ($tenant) {
                return (string) $tenant->id;
            }
        }

        // No tenancy package detected a tenant = not in tenant context
        return null;
    }

    /**
     * Get tenant identifier for display purposes.
     */
    public function getDisplayName(?string $tenantId): string
    {
        if (!$tenantId) {
            return 'No Tenant';
        }

        // Try to get a friendly name from stancl/tenancy
        if (function_exists('tenant') && tenant() && (string) tenant()->id === $tenantId) {
            return tenant()->name ?? tenant()->domain ?? $tenantId;
        }

        // Try to get a friendly name from spatie/laravel-multitenancy
        if (class_exists(\Spatie\Multitenancy\Models\Tenant::class)) {
            $tenant = \Spatie\Multitenancy\Models\Tenant::current();
            if ($tenant && (string) $tenant->id === $tenantId) {
                return $tenant->name ?? $tenantId;
            }
        }

        return $tenantId;
    }
}
