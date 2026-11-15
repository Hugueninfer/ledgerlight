<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * The signed-in user plus their tenant.
 *
 * @property User $resource
 */
class MeResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $tenant = $user->tenant ?? throw new LogicException('A signed-in user always belongs to a tenant.');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'initials' => $user->initials(),
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'brand_color' => $tenant->brand_color,
                // Users only, pending invitations are not members. Explicit tenant filter,
                // so it holds even when no tenant is current.
                'member_count' => User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
            ],
        ];
    }
}
