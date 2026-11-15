<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;

/**
 * The two demo companies from the design: Acme Logistics (four roles' worth of
 * people plus a pending invitation) and Globex Foods (a lone admin). Idempotent.
 */
class DemoTenantsSeeder extends Seeder
{
    public function run(CurrentTenant $currentTenant): void
    {
        $acme = Tenant::query()->updateOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme Logistics', 'brand_color' => '#1D5FC4'],
        );
        $globex = Tenant::query()->updateOrCreate(
            ['slug' => 'globex'],
            ['name' => 'Globex Foods', 'brand_color' => '#16794A'],
        );

        $currentTenant->run($acme, function () {
            $priya = $this->user('Priya Natarajan', 'priya@acmelogistics.com', Role::Admin);
            $this->user('Jordan Reyes', 'jordan@acmelogistics.com', Role::Reviewer);
            $this->user('Dana Whitfield', 'dana@acmelogistics.com', Role::Reviewer);
            $this->user('Maya Chen', 'maya@acmelogistics.com', Role::Uploader);
            $this->user('Alex Romero', 'alex@acmelogistics.com', Role::Uploader);

            Invitation::query()->firstOrCreate(
                ['email' => 'tom.becker@acmelogistics.com'],
                [
                    'role' => Role::Reviewer,
                    'invited_by_user_id' => $priya->id,
                    'expires_at' => now()->addDays(7),
                ],
            );
        });

        $currentTenant->run($globex, function () {
            $this->user('Sam Okafor', 'sam@globexfoods.com', Role::Admin);
        });
    }

    private function user(string $name, string $email, Role $role): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role],
        );
    }
}
