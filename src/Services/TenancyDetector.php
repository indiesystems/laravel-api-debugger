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
        if (class_exists(\Spatie\Multitenancy\Landlord::class)) {
            $tenant = app(\Spatie\Multitenancy\Landlord::class)->currentTenant();
            if ($tenant) {
                return (string) $tenant->id;
            }
        }

        // Try tenancyforlaravel (another common package)
        if (app()->bound('tenancy') && app('tenancy')->initialized) {
            return app('tenancy')->tenant?->id;
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

        // Try to get a more friendly name from common packages
        if (function_exists('tenant') && tenant() && tenant()->id === $tenantId) {
            return tenant()->name ?? tenant()->domain ?? $tenantId;
        }

        return $tenantId;
    }
}
