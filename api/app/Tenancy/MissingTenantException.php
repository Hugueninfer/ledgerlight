<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when a tenant-owned row is created with no tenant_id and no current
 * tenant (a job, seeder or tinker session that forgot to set one), instead of
 * writing a row that belongs to nobody.
 */
final class MissingTenantException extends RuntimeException
{
    public static function creating(Model $model): self
    {
        return new self(sprintf(
            'Cannot create %s without a tenant: set CurrentTenant (e.g. CurrentTenant::run()) or pass tenant_id explicitly.',
            $model::class,
        ));
    }
}
