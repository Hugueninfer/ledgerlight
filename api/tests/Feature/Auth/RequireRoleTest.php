<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'role:reviewer'])->get('/api/_test/review-queue', fn () => ['ok' => true]);
});

it('lets the required role through', function () {
    $this->actingAs(User::factory()->role(Role::Reviewer)->create())
        ->getJson('/api/_test/review-queue')
        ->assertOk();
});

it('lets a higher role through', function () {
    $this->actingAs(User::factory()->role(Role::Admin)->create())
        ->getJson('/api/_test/review-queue')
        ->assertOk();
});

it('forbids a lower role with the forbidden contract plus both roles', function () {
    $res = $this->actingAs(User::factory()->role(Role::Uploader)->create())
        ->getJson('/api/_test/review-queue');

    $res->assertForbidden()->assertExactJson([
        'message' => 'Only reviewers and admins can do this.',
        'code' => 'forbidden',
        'request_id' => $res->headers->get('X-Request-Id'),
        'role' => 'uploader',
        'required' => 'reviewer',
    ]);
});

it('still answers 401 before the role gate when unauthenticated', function () {
    $this->getJson('/api/_test/review-queue')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('runs before route model binding, so a forbidden user cannot probe ids', function () {
    Route::middleware(['api', 'auth:sanctum', 'role:admin'])->get('/api/_test/people/{user}', fn (User $user) => ['id' => $user->id]);

    $this->actingAs(User::factory()->role(Role::Reviewer)->create())
        ->getJson('/api/_test/people/999999')
        ->assertForbidden()
        ->assertJsonPath('required', 'admin');
});
