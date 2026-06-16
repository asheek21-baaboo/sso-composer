# Minimal IdP host guide — reducing boilerplate

This document defines **what `company/sso` already owns**, **what every IdP host must still wire**, and **proposed package additions** so a standalone Laravel app (e.g. `baaboo-sso-dev`) needs minimal custom code.

**Audience:** package maintainers (`sso-composer`) and anyone building a small IdP without copying all of `baaboo-sso`.

**Reference host:** `baaboo-sso-dev` — minimal IdP with project CRUD + login.

**Related:** [implementation-summary.md](./implementation-summary.md) (full protocol + client RP guide).

---

## 1. Design principle

| Layer | Owner | Stable? |
|-------|--------|---------|
| OAuth protocol (authorize query, code TTL, token exchange, JWT claims, JWKS) | **`company/sso`** | Yes — do not fork in apps |
| HTTP endpoints `/oauth/token`, `/jwks`, `/oauth/heartbeat`, `/oauth/session/end` | **`company/sso`** (`SSO_MODE=server`) | Yes |
| HTTP endpoint `/oauth/authorize` + interactive login UI | **Host IdP app** | Yes — stays in host by design |
| User accounts, passwords, admin UI, project registry | **Host IdP app** | Yes |
| Eloquent tables + contract bindings | **Host IdP app** (today) | **Target for publishable defaults** |

The package is a **library**, not a deployable IdP. A minimal IdP is: `company/sso` (server mode) + thin host glue + your admin UI.

---

## 2. What the package provides today (v3+)

### 2.1 Server mode (`SSO_MODE=server`)

Auto-registered routes (`routes/server.php`):

| Method | Path | Action |
|--------|------|--------|
| `POST` | `/oauth/token` | `ExchangeAuthorizationCodeForAccessToken` |
| `POST` | `/oauth/heartbeat` | `TouchOAuthHeartbeat` |
| `POST` | `/oauth/session/end` | `EndOAuthAccessSession` |
| `GET` | `/jwks`, `/.well-known/jwks.json` | RS256 public keys |

### 2.2 Server actions (injectable, host binds stores)

| Class | Responsibility |
|-------|----------------|
| `ResolveOAuthAuthorizeContext` | Validate `project_id`, `redirect_uri`, project active + OAuth configured |
| `IssueOAuthAuthorizationCodeRedirect` | Create one-time code, audit, redirect to client with `?code=` |
| `ExchangeAuthorizationCodeForAccessToken` | Validate code + client credentials → JWT |
| `StartOrResumeSsoAccessSession` | Session/JTI lifecycle + token payload |

### 2.3 Contracts the host **must** bind

| Contract | Purpose |
|----------|---------|
| `OAuthProjectResolver` | Map DB project → `OAuthProject` DTO |
| `OAuthUserResolver` | Map DB user → `OAuthUser` DTO; access + role |
| `OAuthAuthorizationCodeStore` | Persist one-time authorization codes |
| `OAuthSessionStore` | Device-scoped access sessions + visits |
| `OAuthAuditLogger` | Optional audit trail (`NullOAuthAuditLogger` if omitted) |

### 2.4 Shared utilities

| Utility | Use |
|---------|-----|
| `OAuthUrls::authorize($idpUrl, $slug, $redirectUri, $prompt?)` | Build authorize URL |
| `OAuthUrls::redirectUri($appUrl, $path?)` | Build client callback URL |
| `SsoDeviceId` | Device cookie for session scoping |

### 2.5 What the package does **not** provide (host responsibility)

- `GET /oauth/authorize` route and controller
- Login / logout routes and views (Fortify, Breeze, or custom)
- Project admin UI
- Database migrations for SSO tables
- Eloquent implementations of the five contracts

---

## 3. Boilerplate inventory (current minimal host)

From `baaboo-sso-dev`, a working standalone IdP currently requires roughly:

### 3.1 Configuration (`.env`)

```env
SSO_MODE=server
APP_URL=https://idp.example.test

JWT_KEY_ID=idp-1
JWT_ISSUER="${APP_URL}"
JWT_TTL_SECONDS=36000
JWT_AUTHORIZATION_CODE_TTL=60
JWT_PRIVATE_KEY_PATH=storage/app/private/jwt-private.pem
JWT_PUBLIC_KEY_PATH=storage/app/private/jwt-public.pem
```

`JWT_ISSUER` must match what client apps use as `SSO_BASE_URL` (normalized, no trailing slash).

### 3.2 Service provider (contract bindings)

```php
// app/Providers/SsoBridgeServiceProvider.php
$this->app->bind(OAuthProjectResolver::class, EloquentOAuthProjectResolver::class);
$this->app->bind(OAuthUserResolver::class, EloquentOAuthUserResolver::class);
$this->app->bind(OAuthAuthorizationCodeStore::class, EloquentOAuthAuthorizationCodeStore::class);
$this->app->bind(OAuthSessionStore::class, EloquentOAuthSessionStore::class);
$this->app->bind(OAuthAuditLogger::class, EloquentOAuthAuditLogger::class);
```

### 3.3 Routes (host-only)

```php
Route::get('oauth/authorize', OAuthAuthorizeController::class)
    ->middleware(AttachSsoDeviceIdCookie::class)
    ->name('oauth.authorize');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});
```

### 3.4 Authorize controller pattern

The host controller should:

1. Call `ResolveOAuthAuthorizeContext::execute($request->query())`
2. If guest → store `sso.oauth.resume_query` in session → redirect to `login`
3. If authenticated → call `IssueOAuthAuthorizationCodeRedirect` → `redirect()->away($clientUrlWithCode)`
4. Catch `ValidationException` → show OAuth error view

**Important:** `authorizeUri()` for admin “Visit” must use **`config('app.url')`** as IdP base — not `SSO_BASE_URL` from a client app.

```php
OAuthUrls::authorize(
    (string) config('app.url'),
    $project->slug,
    $project->redirectUri(),
);
```

### 3.5 Database tables (host migrations)

| Table | Purpose |
|-------|---------|
| `projects` | `slug`, `url`, `client_id`, `client_secret` (hashed), `status`, `sso_provisions_users` |
| `oauth_authorization_codes` | One-time codes (`code_hash`, `expires_at`, `device_id`, …) |
| `user_activity` | Open JWT sessions (`jti`, `device_id`, `session_start`, …) |
| `project_visits` | Last visit per user/project (optional but used by session store) |
| `auth_audit_logs` | Audit trail (optional if using real `OAuthAuditLogger`) |
| `users` | At minimum: `email`, `password`, `is_active`, `global_role` |

### 3.6 Eloquent models — table name gotchas

Laravel’s inflector may pluralize incorrectly. Set explicitly:

| Model | `$table` |
|-------|----------|
| `OAuthAuthorizationCode` | `oauth_authorization_codes` |
| `UserActivity` | `user_activity` |

### 3.7 Project model methods (host convention)

These mirror `OAuthProject` DTO fields and admin UX:

```php
public function redirectUri(): string;      // rtrim(url).'/oauth/callback'
public function ssoClientConfigured(): bool;  // client_id + client_secret + url
public function authorizeUri(): string;     // OAuthUrls::authorize(app.url, …)
public function canVisit(): bool;           // ssoClientConfigured()
```

---

## 4. Proposed package additions (boilerplate reduction spec)

The following are **recommended for a future `company/sso` release**. None are required for the protocol to work; they standardize the host glue.

### 4.1 `HasOAuthClient` contract + trait

**Problem:** Every IdP duplicates `redirectUri()`, `authorizeUri()`, `ssoClientConfigured()`, `canVisit()`.

**Proposal:**

```php
namespace Company\Sso\Server\Contracts;

interface OAuthClientProject
{
    public function oauthProjectSlug(): string;
    public function oauthClientAppUrl(): string;
    public function oauthClientId(): ?string;
    public function oauthClientSecretHash(): ?string;
    public function oauthProjectIsActive(): bool;
    public function oauthProvisionsUsers(): bool;
}
```

```php
namespace Company\Sso\Server\Concerns;

trait InteractsWithOAuthClient
{
    public function redirectUri(): string
    {
        return OAuthUrls::redirectUri($this->oauthClientAppUrl());
    }

    public function authorizeUri(?string $idpUrl = null): string
    {
        return OAuthUrls::authorize(
            $idpUrl ?? (string) config('app.url'),
            $this->oauthProjectSlug(),
            $this->redirectUri(),
        );
    }

    public function ssoClientConfigured(): bool
    {
        return filled($this->oauthClientId())
            && filled($this->oauthClientSecretHash())
            && filled($this->oauthClientAppUrl());
    }

    public function canVisit(): bool
    {
        return $this->ssoClientConfigured();
    }
}
```

Host `Project` model implements `OAuthClientProject` + uses trait → delete ~30 lines per app.

---

### 4.2 Publishable authorize controller + middleware

**Tag:** `sso-idp-authorize`

**Problem:** Nearly identical `OAuthAuthorizeController` in every minimal IdP.

**Proposal:** Abstract base or invokable controller in the package:

```php
namespace Company\Sso\Server\Http\Controllers;

final class OAuthAuthorizeController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveOAuthAuthorizeContext $resolve,
        IssueOAuthAuthorizationCodeRedirect $issue,
        OAuthUserResolver $users,
    ): RedirectResponse|View {
        // Package-owned flow; delegates login redirect to config:
        // config('sso.routes.login_route_name', 'login')
    }
}
```

**Host customizes only:**

| Hook | Default | Override |
|------|---------|----------|
| Login route name | `login` | `config('sso.routes.login_route_name')` |
| OAuth error view | `sso::oauth.errors` | `config('sso.views.oauth_errors')` |
| Post-login gate | `OAuthUserResolver::mayAccessProject` | already contract |
| `prompt=login` handling | logout + re-login | package default |

**Also publish:** `AttachSsoDeviceIdCookie` middleware (move from host copy to `Company\Sso\Server\Http\Middleware`).

**Host `routes/web.php` after publish:**

```php
Route::get('oauth/authorize', \Company\Sso\Server\Http\Controllers\OAuthAuthorizeController::class)
    ->middleware(\Company\Sso\Server\Http\Middleware\AttachSsoDeviceIdCookie::class)
    ->name('oauth.authorize');
```

---

### 4.3 Publishable Eloquent reference adapters

**Tag:** `sso-idp-eloquent`

**Problem:** ~400 lines duplicated across `baaboo-sso` and `baaboo-sso-dev` (`EloquentOAuth*Store`, resolvers, audit logger).

**Proposal:** Optional namespace `Company\Sso\Server\Eloquent\` with:

| Class | Config key for model class |
|-------|---------------------------|
| `EloquentOAuthProjectResolver` | `sso.eloquent.models.project` |
| `EloquentOAuthUserResolver` | `sso.eloquent.models.user` |
| `EloquentOAuthAuthorizationCodeStore` | `sso.eloquent.models.authorization_code` |
| `EloquentOAuthSessionStore` | `sso.eloquent.models.user_activity` |
| `EloquentOAuthAuditLogger` | `sso.eloquent.models.audit_log` |

**User model interface** (host implements on `App\Models\User`):

```php
interface OAuthAuthenticatable
{
    public function oauthUserId(): int|string;
    public function oauthEmail(): string;
    public function oauthGlobalRole(): string;
    public function oauthIsActive(): bool;
    public function mayAccessOAuthProject(OAuthClientProject $project): bool;
    public function oauthProjectRoleFor(OAuthClientProject $project): string;
}
```

**Minimal host after publish:**

```php
// config/sso.php
'eloquent' => [
    'models' => [
        'project' => \App\Models\Project::class,
        'user' => \App\Models\User::class,
        'authorization_code' => \Company\Sso\Server\Eloquent\Models\OAuthAuthorizationCode::class,
        'user_activity' => \Company\Sso\Server\Eloquent\Models\UserActivity::class,
        'audit_log' => \Company\Sso\Server\Eloquent\Models\AuthAuditLog::class,
    ],
],
```

```php
// AppServiceProvider or SsoBridgeServiceProvider — one line if using defaults:
$this->app->register(\Company\Sso\Server\Eloquent\EloquentSsoServiceProvider::class);
```

---

### 4.4 Publishable migrations

**Tag:** `sso-idp-migrations`

Ship migrations for SSO tables **only** (not `projects` / `users` — those vary per host):

- `oauth_authorization_codes`
- `user_activity` (+ `device_id`)
- `project_visits`
- `auth_audit_logs`

Host runs:

```bash
php artisan vendor:publish --tag=sso-idp-migrations
php artisan migrate
```

Document required columns on host `projects` and `users` tables (section 3.5).

---

### 4.5 `php artisan sso:idp-install` (optional convenience)

Single command that:

1. Publishes config, migrations, authorize stub (if not using package controller)
2. Prints OpenSSL commands to generate JWT key pair
3. Appends `.env.example` server block
4. Lists checklist (seed user, register first project, smoke-test `/jwks`)

---

### 4.6 Config additions (proposed `config/sso.php`)

```php
// Server / IdP host
'routes' => [
    'register' => true,
    'login_route_name' => env('SSO_LOGIN_ROUTE', 'login'),
    'authorize_register' => env('SSO_REGISTER_AUTHORIZE_ROUTE', true), // host can disable if customizing
],
'views' => [
    'oauth_errors' => 'sso::oauth.errors',
],
'eloquent' => [
    'enabled' => env('SSO_ELOQUENT_ADAPTERS', false),
    'models' => [ /* see 4.3 */ ],
],
'session' => [
    'oauth_resume_key' => 'sso.oauth.resume_query',
],
```

---

## 5. Minimal host checklist

### Today (without proposed additions)

- [ ] `composer require company/sso` + `SSO_MODE=server`
- [ ] JWT keys configured (`JWT_*_PATH` or `JWT_*_PEM`)
- [ ] `JWT_ISSUER` = `APP_URL` (IdP public URL)
- [ ] Five contracts bound in a service provider
- [ ] Migrations for SSO tables + `projects` with OAuth columns
- [ ] `GET /oauth/authorize` + login routes
- [ ] `AttachSsoDeviceIdCookie` on authorize route
- [ ] At least one active user + one active project with `client_id` / hashed `client_secret`
- [ ] `GET /jwks` returns keys (smoke test)
- [ ] Authorize → login → redirect to client with `code` (smoke test)
- [ ] Client app: `POST /oauth/token` with code + credentials → JWT (integration test)

### After proposed additions

- [ ] `php artisan vendor:publish --tag=sso-idp-migrations` + migrate
- [ ] `Project` implements `OAuthClientProject` + `InteractsWithOAuthClient`
- [ ] `User` implements `OAuthAuthenticatable`
- [ ] Register `EloquentSsoServiceProvider` OR bind five contracts manually
- [ ] One login route named in `sso.routes.login_route_name`
- [ ] Optional: `php artisan sso:idp-install`

---

## 6. Client app wiring (unchanged)

Internal apps remain **client mode**. They receive from the IdP admin:

```env
SSO_MODE=client
SSO_BASE_URL=https://idp.example.test   # same host as IdP APP_URL
SSO_PROJECT_ID=my-app-slug
SSO_CLIENT_ID=...
SSO_CLIENT_SECRET=...
APP_URL=https://my-app.test
```

Redirect URI registered at IdP: `https://my-app.test/oauth/callback`

The IdP **does not** set `SSO_BASE_URL` pointing elsewhere when it is the standalone server.

---

## 7. File map — package vs host (target state)

```
company/sso (package)                    Host IdP (minimal)
─────────────────────                    ───────────────────
routes/server.php                        routes/web.php
  POST /oauth/token                        GET  /oauth/authorize  ← 4.2
  GET  /jwks                               GET  /login, POST /login
Server/Actions/*                         Project CRUD (optional admin)
Core/Contracts/*                         User model + seed
Server/Http/Controllers/OAuthAuthorize*  (optional if 4.2 adopted)
Server/Eloquent/* (proposed 4.3)         config + App\Models\Project
database/migrations/* (proposed 4.4)     projects + users migrations
Core/Support/OAuthUrls                   —
```

---

## 8. Implementation priority (for package maintainers)

| Priority | Item | Lines saved (est.) | Breaking? |
|----------|------|-------------------|-----------|
| P0 | Document this guide + link from README | — | No |
| P1 | `InteractsWithOAuthClient` + `OAuthClientProject` | ~40/host | No | **Shipped** |
| P1 | Move `AttachSsoDeviceIdCookie` into package | ~25/host | No | **Shipped** (`sso.device_id` middleware) |
| P2 | Package `OAuthAuthorizeController` + error view | ~80/host | No | **Shipped** (opt-in via `SSO_REGISTER_AUTHORIZE_ROUTE=true`) |
| P2 | Publish SSO table migrations | ~80/host | No | **Shipped** (`--tag=sso-idp-migrations`) |
| P3 | `EloquentSsoServiceProvider` + reference models | ~350/host | No |
| P4 | `sso:idp-install` artisan command | DX only | No |

---

## 9. Out of scope (stay in host app)

- Fortify / 2FA / portal roles / policies (see full `baaboo-sso`)
- Project-user pivot and per-project roles (host `OAuthUserResolver` logic)
- Hub / external user provisioning
- Client RP apps (`sso-starter` pattern) — see [implementation-summary.md](./implementation-summary.md)

---

## 10. Version note

Written against **`company/sso` v3.x** server mode as used in `baaboo-sso-dev`.

**Package additions shipped in this repo:** `OAuthClientProject`, `InteractsWithOAuthClient`, `OAuthAuthenticatable` (contract only), `AttachSsoDeviceIdCookie`, `OAuthAuthorizeController`, publishable migrations (`sso-idp-migrations`), and OAuth error view (`sso-idp-authorize`). Enable the authorize route with `SSO_REGISTER_AUTHORIZE_ROUTE=true` and set `SSO_LOGIN_ROUTE=login` for IdP hosts. `EloquentSsoServiceProvider` and `sso:idp-install` are still planned.
