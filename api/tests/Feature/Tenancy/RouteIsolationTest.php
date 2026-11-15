<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * The route isolation harness: the proof behind "tenant isolation is enforced
 * and tested on every route".
 *
 * Every parameterised api/ route is called with resources of tenant A (Acme)
 * by an admin of tenant B (Globex) and must answer 404. A route parameter with
 * no factory below fails the test, so a new route cannot ship untested.
 *
 * Register every new route parameter here: name => fn (Tenant $t) => a model
 * owned by $t (it is created with $t as the current tenant).
 */
function tenantOwnedRouteParameters(): array
{
    return [
        'member' => fn (Tenant $t): Model => User::factory()->for($t)->create(),
    ];
}

/**
 * The route's URI with every {parameter} replaced by a fresh resource of $owner.
 */
function uriWithResourcesOf(RoutingRoute $route, Tenant $owner): string
{
    return preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($route, $owner): string {
        $model = app(CurrentTenant::class)->run($owner, fn () => tenantOwnedRouteParameters()[$m[1]]($owner));
        $field = $route->bindingFieldFor($m[1]);

        return (string) ($field === null ? $model->getRouteKey() : $model->getAttribute($field));
    }, $route->uri());
}

it('never exposes another tenant\'s resources on any parameterized api route', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $owner = User::factory()->for($acme)->create(['role' => Role::Admin]);
    $intruder = User::factory()->for($globex)->create(['role' => Role::Admin]);

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/') && $r->parameterNames() !== []);
    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        foreach ($route->parameterNames() as $param) {
            expect(tenantOwnedRouteParameters())->toHaveKey($param, message: "Add a factory for route parameter {{$param}} in RouteIsolationTest so tenant isolation is tested.");
        }

        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            // Fresh resources per method, so an earlier DELETE cannot make a later 404 vacuous.
            $uri = '/'.uriWithResourcesOf($route, $acme);

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
