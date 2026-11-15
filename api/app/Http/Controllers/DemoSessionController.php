<?php

namespace App\Http\Controllers;

use App\Http\Resources\MeResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DemoSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Rule::in, not a database rule: those bypass Eloquent scopes.
        $data = $request->validate([
            'persona' => ['required', 'string', Rule::in(array_keys(config('demo.personas')))],
        ]);

        /** @var array{tenant: string, email: string} $persona */
        $persona = config('demo.personas')[$data['persona']];

        // Sign-in happens before any tenant is current, so look the user up by
        // tenant slug + email, explicitly outside the tenant scope.
        $tenant = Tenant::query()->where('slug', $persona['tenant'])->firstOrFail();
        $user = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $persona['email'])
            ->firstOrFail();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new MeResource($user->setRelation('tenant', $tenant)))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
