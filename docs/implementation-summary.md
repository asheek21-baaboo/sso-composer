# `company/sso` — Integration Guide

This document explains **what this package provides** and **what each related project must do**. Share it with anyone integrating `baaboo-sso`, `sso-starter`, or a new internal Laravel app.

**Package repo:** [github.com/asheek21-baaboo/sso-composer](https://github.com/asheek21-baaboo/sso-composer)

---

## One-sentence summary

`company/sso` is a **shared OAuth/JWT library** with two modes:

| `SSO_MODE` | Host | Role |
|------------|------|------|
| **`server`** | `baaboo-sso` | IdP — token issue, JWKS, heartbeat, session end |
| **`client`** | Internal apps / `sso-starter` | RP — login redirect, callback, JWT cookie, `sso.auth` |

`/oauth/authorize` (Fortify login) stays in `baaboo-sso` only.

---

## System roles

| Project | Who maintains it | Role |
|---------|------------------|------|
| **`baaboo-sso`** | Platform / SSO team | **IdP** — users, projects, OAuth authorize, token issue, JWKS, session lifecycle |
| **`company/sso`** (this package) | Platform / shared library | **Server + client** — shared protocol; mode via `SSO_MODE` |
| **`sso-starter`** | Platform (template) | Cloneable minimal Laravel app with this package pre-wired |
| **Internal apps** | Product teams | Any Laravel app that `composer require`s this package and configures `.env` |

```
┌─────────────────────┐         ┌──────────────────────────────┐
│     baaboo-sso      │         │  your-app + company/sso    │
│     (IdP)           │         │  (relying party)             │
├─────────────────────┤         ├──────────────────────────────┤
│ /oauth/authorize    │ ◄────── │ GET /login (redirect)        │
│ /oauth/token        │ ◄────── │ POST from callback (package) │
│ /jwks               │ ◄────── │ verify JWT (package)         │
│ /oauth/session/end  │ ◄────── │ logout (optional, package)   │
│ users, projects     │         │ GET /oauth/callback          │
│ admin UI            │         │ sso.auth on /dashboard, …    │
└─────────────────────┘         └──────────────────────────────┘
```

---

## What this package provides

### Routes (auto-registered)

| Method | Path | Route name | Behaviour |
|--------|------|------------|-----------|
| `GET` | `/login` | `sso.login` | Redirect to IdP authorize URL |
| `GET` | `/oauth/callback` | `sso.callback` | Exchange `code` for JWT, set cookie, redirect home |

Disable with `config('sso.routes.register', false)` if you register routes yourself.

### Middleware

| Alias | Class | Behaviour |
|-------|-------|-----------|
| `sso.auth` | `AuthenticateSso` | Read JWT cookie → verify via IdP JWKS → bind `SsoUser` → or redirect to `sso.login` |

### User API

After `sso.auth`, use the facade:

```php
use Company\Sso\Facades\SsoUser;

SsoUser::id();           // JWT sub
SsoUser::email();
SsoUser::projectRole();  // role on this project
SsoUser::globalRole();   // portal-wide role
SsoUser::createUser();   // whether IdP provisions users for this project
```

### Other behaviour

- **`sso_access_token` cookie** — httpOnly JWT after successful callback
- **`sso_device_id` cookie** — stable device UUID (set on callback if missing)
- **Remote JWKS verification** — fetches `{SSO_BASE_URL}/jwks`, cached (`SSO_JWKS_CACHE_SECONDS`, default 3600)
- **`LogoutSsoSession` action** — POST to IdP session end + clear cookies (wire your own logout route)

### Configuration (`config/sso.php`)

| Env variable | Config key | Required | Purpose |
|--------------|------------|----------|---------|
| `SSO_BASE_URL` | `idp_url` | Yes | IdP base URL (no trailing slash) |
| `SSO_PROJECT_ID` | `project_id` | Yes | Project slug (JWT `aud` / `project_id`) |
| `SSO_CLIENT_ID` | `client_id` | Yes | OAuth client UUID |
| `SSO_CLIENT_SECRET` | `client_secret` | Yes | Plain secret (never expose to browser) |
| `APP_URL` | `app_url` | Yes | Builds redirect URI `{APP_URL}/oauth/callback` |
| `SSO_HOME_ROUTE` | `home_route` | No | Named route after login (default `home`) |
| `SSO_JWKS_CACHE_SECONDS` | `jwks_cache_seconds` | No | JWKS cache TTL (default 3600) |

### OAuth protocol (fixed — do not change in apps)

| Item | Value |
|------|-------|
| Authorize | `{IDP}/oauth/authorize?project_id=&redirect_uri=&response_type=code` |
| Token | `POST {IDP}/oauth/token` |
| JWKS | `GET {IDP}/jwks` |
| Redirect URI | `{APP_URL}/oauth/callback` |
| Grant type | `authorization_code` |
| Token type | `Bearer` RS256 JWT |

### What this package does **not** provide

- User database sync / provisioning logic (read `createUser` from JWT; app decides what to do)
- IdP admin UI, project registration, or credential creation
- `/oauth/authorize` (Fortify login on `baaboo-sso`)
- Token signing, JWKS publishing, heartbeat endpoints
- Fortify, Hub, or portal permissions

---

## What `baaboo-sso` must provide (IdP)

The IdP team owns everything on the **left side** of the diagram. Internal apps assume these endpoints exist and behave as documented.

### Required IdP endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /oauth/authorize` | Interactive login + issue authorization code (60s TTL) |
| `POST /oauth/token` | Exchange code + `client_id` / `client_secret` → access JWT |
| `GET /jwks` | Public keys for JWT verification |
| `POST /oauth/session/end` | End session when app logs out (optional but supported by package) |

### Required IdP data per project (registered in admin)

Each internal app needs a **Project** in `baaboo-sso` with:

- Project slug (`SSO_PROJECT_ID`)
- App base URL (must match `APP_URL`; redirect URI = `{url}/oauth/callback`)
- OAuth `client_id` (UUID) and `client_secret` (hashed server-side)
- Active status and user access rules

Platform admin gives devs: `SSO_BASE_URL`, `SSO_PROJECT_ID`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`.

### IdP checklist

- [ ] Project registered with correct app URL and redirect URI
- [ ] OAuth credentials issued to dev team (secret via secure channel only)
- [ ] `GET /oauth/authorize` works with `project_id` + `redirect_uri`
- [ ] `POST /oauth/token` returns `{ access_token, expires_in, token_type }`
- [ ] `GET /jwks` returns valid RS256 keys
- [ ] JWT claims include: `sub`, `email`, `global_role`, `project_id`, `project_role`, `createUser`, `iss`, `aud`, `exp`, `jti`
- [ ] `iss` matches `SSO_BASE_URL` (normalized, no trailing slash mismatch)

> **Note:** Set `SSO_MODE=server` in `baaboo-sso` and bind the five contracts (see [Host integration](#host-integration-server-mode)). Set `SSO_MODE=client` in internal apps with `SSO_*` credentials.

---

## What internal apps must do (RP)

### 1. Install the package

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/asheek21-baaboo/sso-composer" }
    ],
    "require": { "company/sso": "dev-main" }
}
```

```bash
composer update company/sso
```

### 2. Configure `.env`

```env
APP_URL=https://my-app.test

SSO_BASE_URL=https://sso.company.test
SSO_PROJECT_ID=my-app
SSO_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
SSO_CLIENT_SECRET=plain-secret-from-admin
SSO_HOME_ROUTE=home
```

### 3. Add a home route and protect app routes

```php
// routes/web.php
Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('sso.auth')->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
});
```

### 4. Use `SsoUser` in protected code

No Laravel `User` model login — identity comes from the JWT after `sso.auth`.

### 5. Optional: logout route

```php
use Company\Sso\Actions\LogoutSsoSession;

Route::post('/logout', fn (Request $request, LogoutSsoSession $logout) => $logout->execute($request));
```

### Internal app checklist

- [ ] `composer require company/sso`
- [ ] All `SSO_*` and `APP_URL` set in `.env`
- [ ] Redirect URI in IdP matches `{APP_URL}/oauth/callback` exactly
- [ ] Named `home` (or `SSO_HOME_ROUTE`) route exists
- [ ] App routes wrapped in `sso.auth`
- [ ] `/login` redirects to IdP (smoke test)
- [ ] After IdP login, lands on home/dashboard with cookie set
- [ ] `/dashboard` without cookie redirects to `/login`

---

## What `sso-starter` provides

`sso-starter` is a **reference implementation** — clone it instead of wiring from scratch.

| Included | Notes |
|----------|-------|
| `company/sso` via path/VCS repo | Same package as production apps |
| `.env.example` with `SSO_*` placeholders | Copy → fill from admin |
| `/` home route | `SSO_HOME_ROUTE=home` |
| `/dashboard` behind `sso.auth` | Example protected page |
| `/settings` Livewire form | Outputs copy-paste `.env` block (does not write secrets to disk) |
| `README.md` | Quick start for devs |

**sso-starter does not replace** getting credentials from the platform admin or registering the project in `baaboo-sso`.

---

## Package source layout

```
sso-composer/
├── config/sso.php
├── routes/web.php
├── src/
│   ├── Actions/           RedirectToIdpLogin, ExchangeCodeAndStoreToken, LogoutSsoSession
│   ├── Http/
│   │   ├── Controllers/   SsoLoginController, OAuthCallbackController
│   │   └── Middleware/    AuthenticateSso
│   ├── Jwt/               RemoteJwksVerifier
│   ├── Support/           OAuthUrls, SsoDeviceId, AccessTokenCookie
│   ├── Facades/           SsoUser
│   ├── SsoAuthenticatedUser.php
│   └── SsoServiceProvider.php
└── tests/
```

---

## Development & verification (this repo)

```bash
composer install
vendor/bin/pest --compact
vendor/bin/phpstan analyse
vendor/bin/pint
```

| Check | Last known |
|-------|------------|
| Pest | 8 tests passed |
| PHPStan | No errors |

---

## Quick reference: who owns what

| Concern | Owner |
|---------|-------|
| User accounts & passwords | `baaboo-sso` |
| Project / OAuth client registration | `baaboo-sso` admin |
| Issue authorization codes & JWTs | `baaboo-sso` |
| Publish JWKS | `baaboo-sso` |
| Redirect user to login | **`company/sso`** |
| Exchange code for token | **`company/sso`** |
| Store & verify JWT in app | **`company/sso`** |
| Protect app routes | **App dev** (`sso.auth`) |
| Business logic after login | **App dev** (`SsoUser`, provisioning if `createUser`) |
