<?php

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('rejects an invitation whose inviter belongs to another tenant at the database level', function () {
    [$acme, $globex] = Tenant::factory()->count(2)->create();
    $globexAdmin = User::factory()->for($globex)->create(['role' => Role::Admin]);
    expect(fn () => DB::table('invitations')->insert([
        'tenant_id' => $acme->id, 'email' => 'x@acme.test', 'role' => 'reviewer',
        'invited_by_user_id' => $globexAdmin->id, 'expires_at' => now()->addDays(7),
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);   // raw insert bypasses Eloquent on purpose
});

it('accepts an invitation whose inviter belongs to the same tenant', function () {
    $acme = Tenant::factory()->create();
    $acmeAdmin = User::factory()->for($acme)->create(['role' => Role::Admin]);
    DB::table('invitations')->insert([
        'tenant_id' => $acme->id, 'email' => 'x@acme.test', 'role' => 'reviewer',
        'invited_by_user_id' => $acmeAdmin->id, 'expires_at' => now()->addDays(7),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(DB::table('invitations')->count())->toBe(1);
});

it('orders roles uploader < reviewer < admin', function () {
    expect(Role::Admin->atLeast(Role::Reviewer))->toBeTrue()
        ->and(Role::Uploader->atLeast(Role::Reviewer))->toBeFalse();
});
