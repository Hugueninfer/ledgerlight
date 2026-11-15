<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\TeamMemberResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The current tenant's members (admins only, see routes/api.php).
 * Both actions rely on the tenant scope: the list and the bound member
 * only ever come from the signed-in user's tenant.
 */
class TeamMemberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $members = User::query()
            ->orderByRaw('case when role = ? then 0 else 1 end', [Role::Admin->value])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return TeamMemberResource::collection($members);
    }

    public function show(User $member): TeamMemberResource
    {
        return new TeamMemberResource($member);
    }
}
