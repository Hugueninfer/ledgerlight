<?php

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;

/**
 * Looks users up across tenants. Emails are only unique per tenant, so a user
 * is identified by tenant slug + email, outside the tenant scope (no tenant is
 * current when a test sets up).
 */
final class TenantUsers
{
    public static function find(string $tenantSlug, string $email): User
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        return User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->firstOrFail();
    }
}
