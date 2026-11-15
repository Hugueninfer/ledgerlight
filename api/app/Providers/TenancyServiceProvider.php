<?php

namespace App\Providers;

use App\Tenancy\CurrentTenant;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not a plain singleton: Octane and queue workers flush scoped
        // instances between requests and jobs, so a tenant never carries over.
        $this->app->scoped(CurrentTenant::class);
    }
}
