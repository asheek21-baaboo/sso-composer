<?php

use Company\Sso\Http\Controllers\OAuthCallbackController;
use Company\Sso\Http\Controllers\SsoLoginController;
use Illuminate\Support\Facades\Route;

Route::get('/login', SsoLoginController::class)->name('sso.login');
Route::get('/oauth/callback', OAuthCallbackController::class)->name('sso.callback');
