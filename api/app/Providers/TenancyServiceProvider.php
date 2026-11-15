<?php

namespace App\Providers;

use App\Tenancy\CurrentTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignKeyDefinition;
use Illuminate\Support\Fluent;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not a plain singleton: Octane and queue workers flush scoped
        // instances between requests and jobs, so a tenant never carries over.
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        $this->registerBlueprintMacros();
    }

    /**
     * MySQL has no row-level security, so isolation is enforced with keys:
     * every tenant-owned table gets unique(tenant_id, id), and every reference
     * to another tenant-owned row is a composite foreign key including
     * tenant_id, so a row can only point at rows of its own tenant.
     */
    private function registerBlueprintMacros(): void
    {
        // The target of composite foreign keys (MySQL requires an index on them).
        // unique() is documented as IndexDefinition but returns a plain Fluent.
        Blueprint::macro('tenantKey', function (): Fluent {
            return $this->unique(['tenant_id', 'id']);
        });

        // (tenant_id, $column) -> $table(tenant_id, id)
        Blueprint::macro('tenantForeign', function (string $column, string $table): ForeignKeyDefinition {
            return $this->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($table);
        });
    }
}
