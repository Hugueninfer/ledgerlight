<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test helpers shared by every feature test.
 */
trait ActsAsPersona
{
    /**
     * Act as a demo persona from config('demo.personas'), e.g. 'acme-admin'.
     *
     * Emails are only unique per tenant, so the user is looked up by the
     * persona's tenant slug + email, outside the tenant scope (no tenant is
     * current yet when a test starts).
     */
    public function actingAsPersona(string $persona): static
    {
        /** @var array{tenant: string, email: string}|null $config */
        $config = config("demo.personas.{$persona}");

        if ($config === null) {
            throw new InvalidArgumentException("Unknown demo persona [{$persona}].");
        }

        $tenant = Tenant::query()->where('slug', $config['tenant'])->firstOrFail();
        $user = User::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $config['email'])
            ->firstOrFail();

        return $this->actingAs($user);
    }
}

pest()->extend(TestCase::class)->use(RefreshDatabase::class, ActsAsPersona::class)->in('Feature');
