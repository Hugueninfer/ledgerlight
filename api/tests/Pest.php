<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TenantUsers;
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
     * persona's tenant slug + email (see TenantUsers).
     */
    public function actingAsPersona(string $persona): static
    {
        /** @var array{tenant: string, email: string}|null $config */
        $config = config("demo.personas.{$persona}");

        if ($config === null) {
            throw new InvalidArgumentException("Unknown demo persona [{$persona}].");
        }

        return $this->actingAs(TenantUsers::find($config['tenant'], $config['email']));
    }
}

pest()->extend(TestCase::class)->use(RefreshDatabase::class, ActsAsPersona::class)->in('Feature');
