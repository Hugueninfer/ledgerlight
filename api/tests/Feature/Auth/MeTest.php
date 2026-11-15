<?php

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Route;

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

it('sets the current tenant from the authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/me')->assertOk();
    expect(app(CurrentTenant::class)->id())->toBe($user->tenant_id);
});

function registerTenantBoundRoute(): void
{
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/users/{user}', fn (User $user) => ['id' => $user->id]);
}

// One request per test: the test app reuses the scoped CurrentTenant across requests,
// so a second request would inherit the first one's tenant and hide an ordering bug.
it('binds a same-tenant model before the controller runs', function () {
    registerTenantBoundRoute();
    $mine = User::factory()->create();
    $colleague = app(CurrentTenant::class)->run($mine->tenant, fn () => User::factory()->create());
    app(CurrentTenant::class)->forget();

    $this->actingAs($mine)->getJson("/api/_test/users/{$colleague->id}")->assertOk()->assertJsonPath('id', $colleague->id);
});

it('returns 404 with the error contract when binding another tenant\'s model', function () {
    registerTenantBoundRoute();
    $mine = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($mine)->getJson("/api/_test/users/{$stranger->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});
