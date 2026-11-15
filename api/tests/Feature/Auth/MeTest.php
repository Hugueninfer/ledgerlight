<?php

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;

beforeEach(function () {
    $this->withHeaders(['Origin' => 'http://localhost:8088', 'Referer' => 'http://localhost:8088/']);
});

it('requires authentication for me', function () {
    $this->getJson('/api/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('returns the user and tenant, counting only users as members', function () {
    $tenant = Tenant::factory()->create(['slug' => 'initech', 'name' => 'Initech', 'brand_color' => '#112233']);
    $user = app(CurrentTenant::class)->run($tenant, fn () => User::factory()->count(2)->create()->first());
    $other = Tenant::factory()->create();
    app(CurrentTenant::class)->run($other, fn () => User::factory()->count(3)->create());

    $this->actingAs($user)->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('tenant.id', $tenant->id)
        ->assertJsonPath('tenant.slug', 'initech')
        ->assertJsonPath('tenant.brand_color', '#112233')
        ->assertJsonPath('tenant.member_count', 2);
});

it('clears a stale tenant on an unauthenticated request that reaches the route', function () {
    app(CurrentTenant::class)->set(Tenant::factory()->create());
    // Public route: it passes through SetCurrentTenant without a user.
    $this->postJson('/api/demo/sessions', ['persona' => 'root'])->assertUnprocessable();
    expect(app(CurrentTenant::class)->get())->toBeNull();
});
