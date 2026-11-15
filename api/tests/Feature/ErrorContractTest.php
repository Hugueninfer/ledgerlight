<?php

use App\Exceptions\ApiException;
use App\Support\ErrorCode;
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

it('keeps the Allow header on 405 responses', function () {
    $this->post('/api/health')
        ->assertStatus(405)
        ->assertHeader('Allow');
});

it('maps 429 to too_many_requests and keeps Retry-After', function () {
    Route::get('/api/_probe/throttled', fn () => 'ok')->middleware('throttle:1,1');

    $this->get('/api/_probe/throttled')->assertOk();
    $this->get('/api/_probe/throttled')
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

it('maps 419 to csrf_mismatch', function () {
    Route::get('/api/_probe/expired', fn () => abort(419));

    $this->get('/api/_probe/expired')->assertStatus(419)->assertJsonPath('code', 'csrf_mismatch');
});

it('maps other client errors to bad_request and conflicts to conflict', function () {
    Route::get('/api/_probe/bad', fn () => abort(400));
    Route::get('/api/_probe/clash', fn () => abort(409));

    $this->get('/api/_probe/bad')->assertStatus(400)->assertJsonPath('code', 'bad_request');
    $this->get('/api/_probe/clash')->assertStatus(409)->assertJsonPath('code', 'conflict');
});

it('renders an ApiException verbatim with its extra fields inside the contract', function () {
    Route::get('/api/_probe/custom', fn () => throw new ApiException(
        403,
        ErrorCode::Forbidden,
        'Only admins can do this.',
        ['required' => 'admin', 'code' => 'overridden', 'request_id' => 'req_spoofed'],
        ['X-Probe' => 'kept'],
    ));

    $res = $this->get('/api/_probe/custom');

    $res->assertForbidden()
        ->assertExactJson([
            'message' => 'Only admins can do this.',
            'code' => 'forbidden',
            'request_id' => $res->headers->get('X-Request-Id'),
            'required' => 'admin',
        ])
        ->assertHeader('X-Probe', 'kept');
    expect($res->json('request_id'))->toMatch('/^req_[A-Za-z0-9]{8}$/');
});

it('keeps an ApiException message on a 5xx outside debug mode', function () {
    config(['app.debug' => false]);
    Route::get('/api/_probe/upstream', fn () => throw new ApiException(503, ErrorCode::ServiceUnavailable, 'Tallyline is down, retrying soon.'));

    $this->get('/api/_probe/upstream')
        ->assertStatus(503)
        ->assertJsonPath('code', 'service_unavailable')
        ->assertJsonPath('message', 'Tallyline is down, retrying soon.');
});
