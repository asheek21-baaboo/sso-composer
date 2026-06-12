# company/sso

Shared OAuth 2.0 authorization-code + RS256 JWT SSO for Laravel applications.

One package, two modes:

| Mode | Host app | Role |
|------|----------|------|
| `server` | IdP (e.g. `baaboo-sso`) | Issues codes/tokens, JWKS, heartbeat, session end |
| `client` | Internal apps (e.g. `sso-starter`) | Login redirect, callback, JWT cookie, auth middleware |

## Requirements

- PHP ^8.3
- Laravel ^13 (via `illuminate/*` ^13)

## Installation

```bash
composer require company/sso
```

For local development with a path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../sso-composer" }
    ],
    "require": {
        "company/sso": "@dev"
    }
}
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=sso-config
```

## Configuration

Set `SSO_MODE` in `.env`:

### Server (IdP)

```env
SSO_MODE=server
JWT_ISSUER=https://sso.company.test
JWT_KEY_ID=company-sso-1
JWT_TTL_SECONDS=36000
JWT_AUTHORIZATION_CODE_TTL=60
JWT_PRIVATE_KEY_PEM=...
JWT_PUBLIC_KEY_PEM=...
# or JWT_PRIVATE_KEY_PATH / JWT_PUBLIC_KEY_PATH
```

The host app must bind the five contracts (see [Host integration](#host-integration-server-mode)).

### Client (RP)

```env
SSO_MODE=client
APP_URL=https://my-app.test
SSO_BASE_URL=https://sso.company.test
SSO_PROJECT_ID=my-app
SSO_CLIENT_ID=
SSO_CLIENT_SECRET=
SSO_HOME_ROUTE=home
```

## Routes

Registered automatically when `config('sso.routes.register')` is `true`.

### Server

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/oauth/token` | Exchange authorization code for access token |
| `POST` | `/oauth/heartbeat` | Keep session alive |
| `POST` | `/oauth/session/end` | End session / revoke token |
| `GET` | `/jwks` | Public signing keys |
| `GET` | `/.well-known/jwks.json` | JWKS (alias) |

Authorize (`/oauth/authorize`) stays in the host IdP app (Fortify session).

### Client

| Method | Path | Name |
|--------|------|------|
| `GET` | `/login` | `sso.login` |
| `GET` | `/oauth/callback` | `sso.callback` |

Protect app routes with the `sso.auth` middleware:

```php
Route::middleware('sso.auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
```

## Client usage

After `sso.auth` middleware runs, read the authenticated user:

```php
use Company\Sso\Facades\SsoUser;

SsoUser::id();
SsoUser::email();
SsoUser::projectRole();
SsoUser::globalRole();
SsoUser::createUser();
```

JWT claims match the fixed protocol (`sub`, `email`, `global_role`, `project_id`, `project_role`, `createUser`, etc.).

## Host integration (server mode)

Implement and bind these contracts in your service provider:

| Contract | Purpose |
|----------|---------|
| `OAuthProjectResolver` | Resolve projects by slug/id |
| `OAuthUserResolver` | User lookup, project access, role |
| `OAuthAuthorizationCodeStore` | Persist one-time codes |
| `OAuthSessionStore` | Device-scoped access sessions |
| `OAuthAuditLogger` | Audit log (optional; defaults to no-op) |

Example (see `baaboo-sso` for full Eloquent adapters):

```php
$this->app->bind(OAuthProjectResolver::class, EloquentOAuthProjectResolver::class);
$this->app->bind(OAuthUserResolver::class, EloquentOAuthUserResolver::class);
$this->app->bind(OAuthAuthorizationCodeStore::class, EloquentOAuthAuthorizationCodeStore::class);
$this->app->bind(OAuthSessionStore::class, EloquentOAuthSessionStore::class);
$this->app->bind(OAuthAuditLogger::class, EloquentOAuthAuditLogger::class);
```

## Development

```bash
composer install
vendor/bin/pest --compact
vendor/bin/phpstan analyse
vendor/bin/pint
```

## Related repos

```
sso-composer/   ← this package (company/sso)
baaboo-sso/     ← IdP (server mode)
sso-starter/    ← minimal client template (client mode)
```

See [docs/implementation-summary.md](docs/implementation-summary.md) for a full build and integration checklist.

## License

Proprietary.
