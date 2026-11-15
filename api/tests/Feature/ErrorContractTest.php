<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('renders unknown api routes as the json error contract even without accept header', function () {
    $res = $this->get('/api/does-not-exist');

    $res->assertNotFound()
        ->assertJsonStructure(['message', 'code', 'request_id'])
        ->assertJsonPath('code', 'not_found');

    expect($res->json('request_id'))->toMatch('/^req_[A-Za-z0-9]{8}$/')
        ->toBe($res->headers->get('X-Request-Id'));
});

it('echoes a valid incoming request id', function () {
    $this->getJson('/api/health', ['X-Request-Id' => 'req_AbCd1234'])
        ->assertHeader('X-Request-Id', 'req_AbCd1234');
});

it('replaces a malformed incoming request id', function () {
    $id = $this->getJson('/api/health', ['X-Request-Id' => '<script>'])->headers->get('X-Request-Id');

    expect($id)->toMatch('/^req_[A-Za-z0-9]{8}$/');
});

it('maps a wrong method on an api route to not_found', function () {
    $this->post('/api/health')
        ->assertStatus(405)
        ->assertJsonPath('code', 'not_found');
});

it('renders validation failures with an errors object', function () {
    Route::post('/api/_probe/validate', fn (Request $request) => $request->validate(['name' => 'required']));

    $this->post('/api/_probe/validate')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonStructure(['message', 'code', 'request_id', 'errors' => ['name']]);
});

it('hides exception details on server errors outside debug mode', function () {
    config(['app.debug' => false]);
    Route::get('/api/_probe/boom', fn () => throw new RuntimeException('secret internals'));

    $res = $this->get('/api/_probe/boom');

    $res->assertStatus(500)->assertJsonPath('code', 'server_error')->assertJsonPath('message', 'Something went wrong.');
    expect($res->getContent())->not->toContain('secret internals');
});
