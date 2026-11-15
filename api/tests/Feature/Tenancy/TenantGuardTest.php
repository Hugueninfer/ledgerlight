<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantMismatchException;

it('refuses to create a row for another tenant while a tenant is current', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $globexAdmin = User::factory()->for($globex)->create();

    app(CurrentTenant::class)->run($acme, fn () => Invitation::factory()->create([
        'tenant_id' => $globex->id,
        'invited_by_user_id' => $globexAdmin->id,
    ]));
})->throws(TenantMismatchException::class);

it('creates a row whose explicit tenant_id matches the current tenant', function () {
    $acme = Tenant::factory()->create();
    $user = app(CurrentTenant::class)->run($acme, fn () => User::factory()->for($acme)->create());
    expect($user->tenant_id)->toBe($acme->id);
});

it('creates a row with an explicit tenant_id when no tenant is current', function () {
    $acme = Tenant::factory()->create();
    app(CurrentTenant::class)->forget();
    $user = User::factory()->for($acme)->create();
    expect(User::withoutGlobalScopes()->find($user->id)?->tenant_id)->toBe($acme->id);
});

it('refuses to move a row to another tenant', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $user = User::factory()->for($acme)->create();
    $user->tenant_id = $globex->id;
    $user->save();
})->throws(TenantMismatchException::class);

it('refuses to move a row to another tenant through forceFill', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $user = User::factory()->for($acme)->create();
    $user->forceFill(['tenant_id' => $globex->id])->save();
})->throws(TenantMismatchException::class);

it('still updates other attributes of a tenant-owned row', function () {
    $acme = Tenant::factory()->create();
    $user = User::factory()->for($acme)->create(['name' => 'Before']);
    $user->update(['name' => 'After']);
    expect($user->fresh()?->name)->toBe('After');
});
