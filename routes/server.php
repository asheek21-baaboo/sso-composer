<?php

use Company\Sso\Server\Http\Controllers\OAuthHeartbeatController;
use Company\Sso\Server\Http\Controllers\OAuthJwksController;
use Company\Sso\Server\Http\Controllers\OAuthSessionEndController;
use Company\Sso\Server\Http\Controllers\OAuthTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/oauth/token', OAuthTokenController::class)->middleware('throttle:60,1');
Route::post('/oauth/heartbeat', OAuthHeartbeatController::class)->middleware('throttle:120,1');
Route::post('/oauth/session/end', OAuthSessionEndController::class)->middleware('throttle:120,1');
Route::get('/jwks', OAuthJwksController::class)->middleware('throttle:240,1');
Route::get('/.well-known/jwks.json', OAuthJwksController::class)->middleware('throttle:240,1');
