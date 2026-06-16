<?php

namespace Company\Sso;

use Company\Sso\Client\Http\Middleware\AuthenticateSso;
use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Jwt\AccessTokenIssuer;
use Company\Sso\Core\Jwt\AccessTokenVerifier;
use Company\Sso\Core\Jwt\JwtKeyLoader;
use Company\Sso\Core\Jwt\RemoteJwksVerifier;
use Company\Sso\Core\NullOAuthAuditLogger;
use Company\Sso\Server\Http\Controllers\OAuthAuthorizeController;
use Company\Sso\Server\Http\Middleware\AttachSsoDeviceIdCookie;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sso.php', 'sso');

        $this->app->singleton(JwtKeyLoader::class, fn (): JwtKeyLoader => JwtKeyLoader::fromApplicationConfig());
        $this->app->singleton(AccessTokenIssuer::class);
        $this->app->singleton(AccessTokenVerifier::class);
        $this->app->singleton(RemoteJwksVerifier::class);

        if (! $this->app->bound(OAuthAuditLogger::class)) {
            $this->app->singleton(OAuthAuditLogger::class, NullOAuthAuditLogger::class);
        }

        $this->app['router']->aliasMiddleware('sso.auth', AuthenticateSso::class);
        $this->app['router']->aliasMiddleware('sso.device_id', AttachSsoDeviceIdCookie::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sso');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sso.php' => config_path('sso.php'),
            ], 'sso-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/sso'),
            ], 'sso-idp-authorize');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'sso-idp-migrations');
        }

        if (! config('sso.routes.register', true)) {
            return;
        }

        if (env('SSO_MODE', 'client') === 'server') {
            Route::middleware((array) config('sso.routes.server_middleware', ['api']))
                ->prefix((string) config('sso.routes.server_prefix', ''))
                ->group(__DIR__.'/../routes/server.php');

            if (config('sso.routes.authorize_register', false)) {
                $authorizeMiddleware = array_merge(
                    (array) config('sso.routes.authorize_middleware', ['web']),
                    [AttachSsoDeviceIdCookie::class],
                );

                Route::middleware($authorizeMiddleware)
                    ->prefix((string) config('sso.routes.server_prefix', ''))
                    ->get('/oauth/authorize', OAuthAuthorizeController::class)
                    ->name('oauth.authorize');
            }
        } else {
            Route::middleware((array) config('sso.routes.client_middleware', ['web']))
                ->prefix((string) config('sso.routes.client_prefix', ''))
                ->group(__DIR__.'/../routes/client.php');
        }
    }
}
