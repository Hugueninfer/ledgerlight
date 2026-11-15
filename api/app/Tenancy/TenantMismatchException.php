<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when code tries to write a tenant-owned row into a tenant other than
 * the current one, or to move an existing row to another tenant. The tenant
 * comes from the authenticated user (via CurrentTenant), never from input.
 */
final class TenantMismatchException extends RuntimeException
{
    public static function creating(Model $model, int $currentTenantId, mixed $givenTenantId): self
    {
        return new self(sprintf(
            'Cannot create %s for tenant %s while tenant %d is current.',
            $model::class,
            is_scalar($givenTenantId) ? (string) $givenTenantId : get_debug_type($givenTenantId),
            $currentTenantId,
        ));
    }

    public static function moving(Model $model): self
    {
        return new self(sprintf(
            'Cannot change tenant_id of %s #%s: rows never move between tenants.',
            $model::class,
            is_scalar($key = $model->getKey()) ? (string) $key : '?',
        ));
    }
}
