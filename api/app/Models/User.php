<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * A member of exactly one tenant. Email is unique per tenant, not globally.
 * Users sign in through demo personas, so there is no password.
 * tenant_id is deliberately not fillable: it comes from CurrentTenant.
 *
 * @property Role $role
 */
#[Fillable(['name', 'email', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use BelongsToTenant;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Up to two capital letters for the avatar, e.g. "Priya Natarajan" -> "PN".
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => Role::class,
        ];
    }
}
