<?php

use Database\Seeders\DemoTenantsSeeder;
use Tests\Support\TenantUsers;

beforeEach(fn () => $this->seed(DemoTenantsSeeder::class));

it('lists only own tenant members for an admin', function () {
    $this->actingAsPersona('acme-admin')->getJson('/api/team/members')
        ->assertOk()->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0.name', 'Priya Natarajan');
});

it('orders admins first, then everyone by name', function () {
    $this->actingAsPersona('acme-admin')->getJson('/api/team/members')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Priya Natarajan', 'Alex Romero', 'Dana Whitfield', 'Jordan Reyes', 'Maya Chen']);
});

it('shows each member as id, name, email, role and initials', function () {
    $maya = TenantUsers::find('acme', 'maya@acmelogistics.com');

    $this->actingAsPersona('acme-admin')->getJson('/api/team/members')
        ->assertOk()
        ->assertJsonPath('data.4', [
            'id' => $maya->id,
            'name' => 'Maya Chen',
            'email' => 'maya@acmelogistics.com',
            'role' => 'uploader',
            'initials' => 'MC',
        ]);
});

it('shows the other tenant only its own members', function () {
    $this->actingAsPersona('globex-admin')->getJson('/api/team/members')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'sam@globexfoods.com');
});

it('shows one member of the own tenant', function () {
    $jordan = TenantUsers::find('acme', 'jordan@acmelogistics.com');

    $this->actingAsPersona('acme-admin')->getJson("/api/team/members/{$jordan->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $jordan->id)
        ->assertJsonPath('data.role', 'reviewer')
        ->assertJsonPath('data.initials', 'JR');
});

it('returns 404 for another tenant\'s member', function () {
    $jordan = TenantUsers::find('acme', 'jordan@acmelogistics.com');

    $this->actingAsPersona('globex-admin')->getJson("/api/team/members/{$jordan->id}")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('forbids non-admins', function () {
    $this->actingAsPersona('acme-uploader')->getJson('/api/team/members')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden')
        ->assertJsonPath('role', 'uploader')
        ->assertJsonPath('required', 'admin');
});

it('forbids a reviewer from showing a member, with the admin message', function () {
    $maya = TenantUsers::find('acme', 'maya@acmelogistics.com');

    $this->actingAsPersona('acme-reviewer')->getJson("/api/team/members/{$maya->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only admins can do this.')
        ->assertJsonPath('role', 'reviewer')
        ->assertJsonPath('required', 'admin');
});

it('checks the role before looking the member up', function () {
    // A non-admin learns nothing about which ids exist: 403 either way, never 404.
    $this->actingAsPersona('acme-uploader')->getJson('/api/team/members/999999')
        ->assertForbidden();
});

it('requires authentication for the team', function () {
    $this->getJson('/api/team/members')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});
