<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Database\Seeders\DemoTenantsSeeder;

beforeEach(function () {
    $this->seed(DemoTenantsSeeder::class);
    // Sanctum only starts a session for requests that come from a stateful frontend origin.
    $this->withHeaders(['Origin' => 'http://localhost:8088', 'Referer' => 'http://localhost:8088/']);
});

it('signs in as a persona and returns me', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'acme-reviewer'])
        ->assertCreated()
        ->assertJsonPath('user.name', 'Jordan Reyes')
        ->assertJsonPath('user.role', 'reviewer')
        ->assertJsonPath('user.initials', 'JR')
        ->assertJsonPath('tenant.name', 'Acme Logistics')
        ->assertJsonPath('tenant.member_count', 5);
    $this->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'jordan@acmelogistics.com');
});

it('rejects an unknown persona with the validation contract', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'root'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('persona');
});

it('rejects a missing or non-string persona', function () {
    $this->postJson('/api/demo/sessions', [])->assertJsonValidationErrors('persona');
    $this->postJson('/api/demo/sessions', ['persona' => ['acme-admin']])->assertJsonValidationErrors('persona');
});

it('signs out', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'globex-admin']);
    $this->deleteJson('/api/demo/sessions/current')->assertNoContent();
    // The test app is reused across requests: drop the guard's cached user to act like a fresh request.
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/me')->assertUnauthorized();
});

it('regenerates the session id on login', function () {
    $this->getJson('/api/me'); // starts a session
    $before = session()->getId();
    $this->postJson('/api/demo/sessions', ['persona' => 'acme-admin'])->assertCreated();
    expect(session()->getId())->not->toBe($before);
});

it('regenerates the csrf token on logout', function () {
    $this->postJson('/api/demo/sessions', ['persona' => 'acme-admin']);
    $before = session()->token();
    expect($before)->not->toBeNull();
    $this->deleteJson('/api/demo/sessions/current')->assertNoContent();
    expect(session()->token())->not->toBeNull()->and(session()->token())->not->toBe($before);
});

it('resolves each persona to its own tenant even when emails repeat across tenants', function () {
    config()->set('demo.personas.clone-admin', ['tenant' => 'acme', 'email' => 'sam@globexfoods.com']);
    $clone = Tenant::query()->where('slug', 'acme')->firstOrFail();
    app(CurrentTenant::class)->run($clone, fn () => User::factory()->create([
        'name' => 'Sam Clone', 'email' => 'sam@globexfoods.com',
    ]));

    $this->postJson('/api/demo/sessions', ['persona' => 'clone-admin'])
        ->assertCreated()
        ->assertJsonPath('user.name', 'Sam Clone')
        ->assertJsonPath('tenant.slug', 'acme');
});

it('seeds the demo tenants idempotently', function () {
    $this->seed(DemoTenantsSeeder::class);
    expect(Tenant::count())->toBe(2)
        ->and(User::withoutGlobalScopes()->count())->toBe(6)
        ->and(Invitation::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses a non-stateful client with the error contract instead of a 500', function () {
    $this->withHeaders(['Origin' => 'http://evil.example', 'Referer' => 'http://evil.example/'])
        ->postJson('/api/demo/sessions', ['persona' => 'acme-admin'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'bad_request')
        ->assertJsonPath('message', 'Demo sign-in needs a browser session: request /sanctum/csrf-cookie from the app first.')
        ->assertJsonStructure(['message', 'code', 'request_id']);
});
