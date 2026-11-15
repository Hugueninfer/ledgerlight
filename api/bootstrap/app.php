<?php

use App\Http\Middleware\AssignRequestId;
use App\Support\ErrorCode;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Error contract: every API error is { message, code, request_id } (+ errors on 422).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($isApi): Response {
            if (! $isApi($request)) {
                return $response;
            }

            $status = $response->getStatusCode();
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
