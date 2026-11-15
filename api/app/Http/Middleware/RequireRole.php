<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\ErrorCode;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate by role, used as `role:<role>` (e.g. `role:admin`). Roles are
 * ordered (uploader < reviewer < admin), so a higher role always passes.
 *
 * A failure is 403 `forbidden` plus the user's `role` and the `required` one,
 * so the client can render its 403 state. The message names the role, not the
 * page: the same gate guards pages and actions, and the client knows which
 * page it is showing (e.g. "Team settings").
 *
 * It runs before route model binding (see the priority list in
 * bootstrap/app.php), so a forbidden user never learns whether an id exists.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string $required): Response
    {
        $requiredRole = Role::from($required);
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->role->atLeast($requiredRole)) {
            throw new ApiException(403, ErrorCode::Forbidden, self::message($requiredRole), [
                'role' => $user->role->value,
                'required' => $requiredRole->value,
            ]);
        }

        return $next($request);
    }

    private static function message(Role $required): string
    {
        return match ($required) {
            Role::Admin => 'Only admins can do this.',
            Role::Reviewer => 'Only reviewers and admins can do this.',
            // Unreachable today (every member is at least an uploader), kept for exhaustiveness.
            Role::Uploader => 'Only team members can do this.',
        };
    }
}
