<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * The tenant the current unit of work (request, job, seeder) acts for.
 *
 * Registered as a scoped singleton, so it resets between requests and
 * queue jobs in long-running workers. In HTTP it is set from the
 * authenticated user, never from anything in the request itself.
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    /**
     * Run $callback as $tenant, then restore whatever tenant was set before,
     * even if the callback throws.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
