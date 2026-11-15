<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['email', 'role', 'invited_by_user_id', 'accepted_at', 'expires_at'])]
class Invitation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
