# company/sso

Laravel SSO **client** library for internal apps that authenticate against the company IdP (`baaboo-sso`).

Devs do **not** install or run the core SSO system. They clone a minimal Laravel app, add this package, configure OAuth credentials, and get:

- Redirect to IdP login
- OAuth callback + **authorization-code token exchange**
- JWT stored in an httpOnly cookie
- **`sso.auth` middleware** + `SsoUser` facade

**Repository:** [github.com/asheek21-baaboo/sso-composer](https://github.com/asheek21-baaboo/sso-composer)

## How it fits together

```
baaboo-sso (IdP)          your-app + company/sso (RP)
─────────────────         ────────────────────────────
/oauth/authorize    ←──   redirect to login
/oauth/token        ←──   POST code exchange (package)
/jwks               ←──   verify JWT (package)
/oauth/heartbeat            (optional, host app)
/oauth/session/end          logout (package)

/login, /oauth/callback     registered by package
/dashboard                  protected with sso.auth
```

The IdP owns users, projects, and token issuance. This package is only the **relying-party** side.

## Requirements

- PHP ^8.3
- Laravel ^13 (via `illuminate/*` ^13)
- OAuth client credentials from the platform admin (`SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, project slug)

## Installation

This package is not on Packagist. Add the GitHub repository to your app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/asheek21-baaboo/sso-composer"
        }
    ],
    "require": {
        "company/sso": "dev-main"
    }
}
```

```bash
composer update company/sso
```

Local path checkout (monorepo / Laragon):

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

```env
APP_URL=https://my-app.test

SSO_BASE_URL=https://sso.company.test
SSO_PROJECT_ID=my-app
SSO_CLIENT_ID=
SSO_CLIENT_SECRET=
SSO_HOME_ROUTE=home
```

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Builds `{APP_URL}/oauth/callback` redirect URI |
| `SSO_BASE_URL` | IdP base URL (no trailing slash) |
| `SSO_PROJECT_ID` | Project slug (`aud` / `project_id` in JWT) |
| `SSO_CLIENT_ID` | OAuth client UUID |
| `SSO_CLIENT_SECRET` | Plain secret from admin (server-side only) |
| `SSO_HOME_ROUTE` | Named route after successful callback |

Optional: `SSO_JWKS_CACHE_SECONDS` (default `3600`).

## Routes (registered by the package)

| Method | Path | Name |
|--------|------|------|
| `GET` | `/login` | `sso.login` |
| `GET` | `/oauth/callback` | `sso.callback` |

Protect your app:

```php
Route::middleware('sso.auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
```

## Authenticated user

After `sso.auth` runs:

```php
use Company\Sso\Facades\SsoUser;

SsoUser::id();
SsoUser::email();
SsoUser::projectRole();
SsoUser::globalRole();
SsoUser::createUser(); // whether IdP provisions users for this project
```

JWT is verified via remote JWKS from `{SSO_BASE_URL}/jwks`.

## Starter app

See `sso-starter` — minimal Laravel template with this package pre-wired.

## Development (this repo)

```bash
git clone https://github.com/asheek21-baaboo/sso-composer.git
cd sso-composer
composer install
vendor/bin/pest --compact
vendor/bin/phpstan analyse
vendor/bin/pint
```

## Further reading

- [docs/implementation-summary.md](docs/implementation-summary.md) — build history and integration notes

## License

Proprietary.
