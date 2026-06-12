<?php

use Company\Sso\Client\Http\Controllers\OAuthCallbackController;
use Company\Sso\Client\Http\Controllers\SsoLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', SsoLoginController::class)->name('sso.login');
Route::get('/oauth/callback', OAuthCallbackController::class)->name('sso.callback');
