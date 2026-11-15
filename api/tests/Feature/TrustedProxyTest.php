<?php

use Illuminate\Support\Facades\Route;

it('trusts forwarded headers from the reverse proxy', function () {
    Route::get('/_test/request-info', fn () => response()->json([
        'secure' => request()->isSecure(),
        'ip' => request()->ip(),
    ]));

    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-For' => '203.0.113.7',
    ])->getJson('/_test/request-info')
        ->assertOk()
        ->assertExactJson(['secure' => true, 'ip' => '203.0.113.7']);
});
