<?php

namespace IndieSystems\ApiDebugger\Services;

use Illuminate\Http\Request;

class TenancyDetector
{
    /**
     * Detect the current tenant ID from the request.
     *
     * Checks multiple sources in order of priority:
     * 1. Common tenancy packages (stancl/tenancy, spatie/laravel-multitenancy)
     * 2. Request header (configurable)
     * 3. Subdomain
     * 4. Route parameter
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

        // Check header
        $headerName = config('api-debugger.tenancy.header', 'X-Tenant-ID');
        if ($tenantId = $request->header($headerName)) {
            return $tenantId;
        }

        // Check route parameter
        if ($tenantId = $request->route('tenant')) {
            return (string) $tenantId;
        }

        // Check subdomain
        $host = $request->getHost();
        $parts = explode('.', $host);

        // If more than 2 parts (e.g., tenant.example.com), first part might be tenant
        if (count($parts) > 2) {
            $subdomain = $parts[0];
            // Exclude common non-tenant subdomains
            if (!in_array($subdomain, ['www', 'api', 'app', 'admin', 'mail', 'ftp'])) {
                return $subdomain;
            }
        }

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
