<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SetCurrentTenant;
use App\Support\ErrorCode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        // Cookie-based SPA auth: Sanctum starts the session for requests from SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();

        // The tenant must be current before route model binding runs, otherwise a binding
        // resolves unscoped. The priority list decides order across the whole stack.
        $middleware->api(append: [SetCurrentTenant::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentTenant::class);

        // Role gate, e.g. role:admin. Before binding too: a forbidden user gets 403 whatever the id.
        $middleware->alias(['role' => RequireRole::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: RequireRole::class);

        // There are no web login routes: without this, Authenticate calls route('login') for a
        // signed-out request that does not send Accept: application/json and that 500s.
        // Returning null lets the AuthenticationException render as the 401 error contract.
        $middleware->redirectGuestsTo(fn () => null);

        // Caddy is the only thing that reaches this app (port 8000 is not published), so every
        // forwarded header can be trusted: real client IP, scheme, host and port.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*', 'sanctum/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Error contract: every API error is { message, code, request_id } (+ errors on 422).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isApi): Response {
            if (! $isApi($request)) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($e instanceof ApiException) {
                // The only opt-in to a custom message: written for the user by the code that throws it.
                $body = [
                    'message' => $e->getMessage(),
                    'code' => $e->errorCode->value,
                    'request_id' => $request->attributes->get('request_id'),
                ] + $e->extra;
            } else {
                $code = ErrorCode::fromStatus($status);

                // 4xx messages are fixed per code; 5xx only expose the real message in debug mode.
                $message = $status >= 500 && $status !== 503 && config('app.debug')
                    ? $e->getMessage()
                    : $code->message();

                $body = [
                    'message' => $message,
                    'code' => $code->value,
                    'request_id' => $request->attributes->get('request_id'),
                ];

                if ($e instanceof ValidationException) {
                    $body['errors'] = $e->errors();
                }
            }

            $json = new JsonResponse($body, $status);

            // Keep headers from the original response (Allow, Retry-After, X-RateLimit-*, WWW-Authenticate...).
            foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
                if (in_array(strtolower($name), ['content-type', 'content-length', 'cache-control', 'date'], true)) {
                    continue;
                }
                $json->headers->set($name, $values);
            }
            foreach ($response->headers->getCookies() as $cookie) {
                $json->headers->setCookie($cookie);
            }
            $json->headers->set('X-Request-Id', $body['request_id']);

            return $json;
        });
    })->create();
