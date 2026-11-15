<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every tenant-owned model: scopes queries to the current tenant and
 * guards writes so a row always belongs to exactly one tenant, for good:
 *
 * - creating: tenant_id is filled from CurrentTenant; an explicit tenant_id
 *   must match the current tenant (it may differ only when no tenant is
 *   current, e.g. seeders and factories); no tenant at all throws.
 * - updating: tenant_id can never change.
 *
 * These are model events, so query-builder writes bypass them; the composite
 * foreign keys and NOT NULL tenant_id are the database-level backstop.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $currentTenantId = app(CurrentTenant::class)->id();
            $givenTenantId = $model->getAttribute('tenant_id');

            if ($givenTenantId === null) {
                if ($currentTenantId === null) {
                    throw MissingTenantException::creating($model);
                }

                $model->setAttribute('tenant_id', $currentTenantId);

                return;
            }

            if ($currentTenantId !== null && (string) $givenTenantId !== (string) $currentTenantId) {
                throw TenantMismatchException::creating($model, $currentTenantId, $givenTenantId);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenantMismatchException::moving($model);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
