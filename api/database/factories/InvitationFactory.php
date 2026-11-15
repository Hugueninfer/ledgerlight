<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The current tenant when one is set, otherwise a fresh tenant.
            'tenant_id' => fn () => app(CurrentTenant::class)->id() ?? Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Reviewer,
            // The inviter must belong to the invitation's own tenant (the composite
            // foreign key rejects anything else). When tenant_id is left null for
            // the model to fill in, that tenant is the current one.
            'invited_by_user_id' => fn (array $attributes) => User::factory()->state([
                'tenant_id' => $attributes['tenant_id'] ?? app(CurrentTenant::class)->id(),
                'role' => Role::Admin,
            ]),
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
