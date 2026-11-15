<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\MissingTenantException;
use Illuminate\Database\UniqueConstraintViolationException;

it('only returns rows of the current tenant', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    User::factory()->for($acme)->count(2)->create();
    User::factory()->for($globex)->create();
    app(CurrentTenant::class)->set($globex);
    expect(User::count())->toBe(1);
});

it('fills tenant_id from the current tenant on create', function () {
    $acme = Tenant::factory()->create();
    app(CurrentTenant::class)->run($acme, fn () => Invitation::factory()->create(['tenant_id' => null]));
    expect(Invitation::withoutGlobalScopes()->first()->tenant_id)->toBe($acme->id);
});

it('refuses to create a tenant-owned row without a tenant', function () {
    app(CurrentTenant::class)->forget();
    User::factory()->make(['tenant_id' => null])->save();
})->throws(MissingTenantException::class);

it('allows the same email in two tenants but not twice in one', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    User::factory()->for($acme)->create(['email' => 'priya@acmelogistics.com']);
    User::factory()->for($globex)->create(['email' => 'priya@acmelogistics.com']);
    expect(fn () => User::factory()->for($acme)->create(['email' => 'priya@acmelogistics.com']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('restores the previous tenant after run, even when the callback throws', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $current = app(CurrentTenant::class);
    $current->set($acme);

    expect(fn () => $current->run($globex, fn () => throw new RuntimeException('boom')))
        ->toThrow(RuntimeException::class);

    expect($current->id())->toBe($acme->id);
});

it('does not filter when no tenant is set', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    User::factory()->for($acme)->create();
    User::factory()->for($globex)->create();
    app(CurrentTenant::class)->forget();
    expect(User::count())->toBe(2);
});
