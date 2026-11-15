<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
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
                'initials' => $this->initials($user),
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

    private function initials(User $user): string
    {
        return Str::of($user->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
