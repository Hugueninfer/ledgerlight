<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

it('reports ok when database and redis respond', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]);
});

it('returns 503 json naming the failing check when redis is down', function () {
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('down'));

    $this->get('/api/health') // no Accept header on purpose
        ->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.redis', 'fail')
        ->assertJsonPath('checks.database', 'ok');
});

it('returns 503 json naming the failing check when mysql is down', function () {
    DB::shouldReceive('select')->andThrow(new RuntimeException('down'));

    $this->get('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.database', 'fail')
        ->assertJsonPath('checks.redis', 'ok');
});
