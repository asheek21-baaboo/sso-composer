<?php

namespace Company\Sso;

use Company\Sso\Http\Middleware\AuthenticateSso;
use Company\Sso\Jwt\RemoteJwksVerifier;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sso.php', 'sso');

        $this->app->singleton(RemoteJwksVerifier::class);

        $this->app['router']->aliasMiddleware('sso.auth', AuthenticateSso::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sso.php' => config_path('sso.php'),
            ], 'sso-config');
        }

        if (! config('sso.routes.register', true)) {
            return;
        }

        Route::middleware((array) config('sso.routes.middleware', ['web']))
            ->prefix((string) config('sso.routes.prefix', ''))
            ->group(__DIR__.'/../routes/web.php');
    }
}
