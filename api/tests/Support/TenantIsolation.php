<?php

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;

/**
 * Registry and helpers for the route isolation harness
 * (tests/Feature/Tenancy/RouteIsolationTest.php).
 *
 * Every route parameter of a parameterised api/ route must be registered in
 * ROUTE_PARAMETERS: name => static fn (Tenant $t) => a model owned by $t (it
 * is created with $t as the current tenant). An unregistered parameter fails
 * the harness.
 */
final class TenantIsolation
{
    /** @var array<string, Closure(Tenant): Model> */
    public const ROUTE_PARAMETERS = [
        'member' => static function (Tenant $t): Model {
            return User::factory()->for($t)->create();
        },
    ];

    /**
     * The route's URI with every {parameter} replaced by a fresh resource of $owner.
     */
    public static function uriWithResourcesOf(Route $route, Tenant $owner): string
    {
        return (string) preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($route, $owner): string {
            $model = app(CurrentTenant::class)->run($owner, fn (): Model => (self::ROUTE_PARAMETERS[$m[1]])($owner));
            $field = $route->bindingFieldFor($m[1]);

            return (string) ($field === null ? $model->getRouteKey() : $model->getAttribute($field));
        }, $route->uri());
    }
}
