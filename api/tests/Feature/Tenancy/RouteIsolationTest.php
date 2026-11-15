<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Support\TenantIsolation;

/*
 * The route isolation harness: the proof behind "tenant isolation is enforced
 * and tested on every parameterised route".
 *
 * Every parameterised api/ route is called with resources of tenant A (Acme)
 * by an admin of tenant B (Globex) and must answer 404. A route parameter with
 * no factory in Tests\Support\TenantIsolation::ROUTE_PARAMETERS fails the
 * test, so a new route cannot ship untested. Register new parameters there.
 */
it('never exposes another tenant\'s resources on any parameterized api route', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $owner = User::factory()->for($acme)->create(['role' => Role::Admin]);
    $intruder = User::factory()->for($globex)->create(['role' => Role::Admin]);

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/') && $r->parameterNames() !== []);
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        foreach ($route->parameterNames() as $param) {
            expect(TenantIsolation::ROUTE_PARAMETERS)->toHaveKey($param, message: "Add a factory for route parameter {{$param}} in Tests\\Support\\TenantIsolation::ROUTE_PARAMETERS so tenant isolation is tested.");
        }

        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            // Fresh resources per method, so an earlier DELETE cannot make a later 404 vacuous.
            $uri = '/'.TenantIsolation::uriWithResourcesOf($route, $acme);

            // Each request starts with no current tenant, like a fresh production request:
            // the test app reuses one container, so a tenant would otherwise carry over.
            app(CurrentTenant::class)->forget();
            $status = $this->actingAs($intruder)->json($method, $uri)->status();
            expect($status)->toBe(404, "{$method} {$uri} leaked across tenants (answered {$status} to a {$globex->slug} admin)");

            // Control: the owner does reach the route, so the 404 above is the tenant boundary,
            // not a route that 404s for everyone.
            app(CurrentTenant::class)->forget();
            $status = $this->actingAs($owner)->json($method, $uri)->status();
            expect($status)->not->toBe(404, "{$method} {$uri} answers 404 to its own tenant too, so the isolation check above proves nothing");
        }
    }
});
