<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    private const PATTERN = '/^req_[A-Za-z0-9]{8}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(self::HEADER);

        $id = is_string($incoming) && preg_match(self::PATTERN, $incoming) === 1
            ? $incoming
            : 'req_'.Str::random(8);

        $request->attributes->set('request_id', $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
