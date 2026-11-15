<?php

use App\Http\Controllers\DemoSessionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/demo/sessions', [DemoSessionController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/demo/sessions/current', [DemoSessionController::class, 'destroy']);
    Route::get('/me', MeController::class);

    Route::middleware('role:admin')->group(function () {
        Route::get('/team/members', [TeamMemberController::class, 'index']);
        Route::get('/team/members/{member}', [TeamMemberController::class, 'show']);
    });
});
