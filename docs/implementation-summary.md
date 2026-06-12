# Work Summary — `company/sso` Package

Verification-oriented summary of what was built and integrated across three repos.

---

## 1. Package (`sso-composer` → `company/sso`)

### What it is

- Composer **library** (`type: library`, name: `company/sso`)
- Namespace: `Company\Sso`
- Laravel auto-discovery: `SsoServiceProvider`, `SsoUser` facade
- Two modes via `SSO_MODE`: **`server`** (IdP) | **`client`** (RP)

### Structure implemented (39 source files)

| Layer | Contents |
|-------|----------|
| **Core** | Contracts (5), DTOs (4), `AuthAuditAction` enum, `OAuthUrls`, `SsoDeviceId`, JWT classes (`JwtKeyLoader`, `JwtIssuer`, `AccessTokenIssuer`, `AccessTokenVerifier`, `RemoteJwksVerifier`), `NullOAuthAuditLogger` |
| **Server** | 7 actions + 4 controllers (`/oauth/token`, `/oauth/heartbeat`, `/oauth/session/end`, `/jwks`) |
| **Client** | 3 actions, 2 controllers (`/login`, `/oauth/callback`), `AuthenticateSso` middleware, `AccessTokenCookie`, `SsoAuthenticatedUser`, `SsoUser` facade |
| **Config** | `config/sso.php` |
| **Routes** | `routes/server.php`, `routes/client.php` |

### Service provider behavior

- Merges `config/sso.php`
- Binds JWT services (lazy factory for keys)
- Registers `sso.auth` middleware alias
- Loads server or client routes based on `sso.mode`

### Package tests (14 tests)

- **Unit:** `JwtKeyLoader`, `OAuthUrls`, `SsoDeviceId`
- **Server:** token exchange, heartbeat, session end, `createUser` claim
- **Client:** OAuth callback, `AuthenticateSso` middleware

### Test infrastructure fixes

- Added `tests/Pest.php` (Pest only loads from `tests/`, not project root)
- Added `ServerTestCase` / `ClientTestCase` so `sso.mode` is set before provider boot
- Fixed invalid PHP syntax `?int|string` → `int|string|null`
- Fixed middleware test to use `withUnencryptedCookie()`
- Autoloaded `tests/Feature/Server/Fakes.php` via composer `files`
- Added `phpstan.neon` (level 5, `src/`)

### Package bug fix

- `StartOrResumeSsoAccessSession` compares expiry to `now()` instead of `new \DateTimeImmutable()` so Laravel time travel works in tests

---

## 2. IdP integration (`baaboo-sso`)

### Composer

- Path repo: `../sso-composer`
- Require: `"company/sso": "@dev"`

### New bridge layer (`app/Sso/`)

| Adapter | Implements |
|---------|------------|
| `EloquentOAuthProjectResolver` | `OAuthProjectResolver` |
| `EloquentOAuthUserResolver` | `OAuthUserResolver` |
| `EloquentOAuthAuthorizationCodeStore` | `OAuthAuthorizationCodeStore` |
| `EloquentOAuthSessionStore` | `OAuthSessionStore` |
| `EloquentOAuthAuditLogger` | `OAuthAuditLogger` |

Registered in `SsoBridgeServiceProvider` → `bootstrap/providers.php`.

### Updated to use package

- `OAuthAuthorizeController` — package `ResolveOAuthAuthorizeContext`, `IssueOAuthAuthorizationCodeRedirect`, `SsoDeviceId`
- `AttachSsoDeviceIdCookie` — package `SsoDeviceId`
- `CloseStaleUserActivitySessions` — package `CloseUserActivitySession`, `sso.ttl_seconds`

### Removed (moved to package)

- `app/Services/Jwt/*`
- Server OAuth actions (exchange, issue code, resolve context, start/resume session, heartbeat, session end, close session)
- OAuth controllers: Token, JWKS, Heartbeat, SessionEnd
- `routes/oauth.php`
- `app/Support/SsoDeviceId.php`

### Kept in baaboo-sso (per spec)

- `OAuthAuthorizeController`, Fortify, Hub, admin UI, models, `LogAuthAuditEvent`, `RedirectToInteractiveLoginForOAuthAuthorize`, `CloseStaleUserActivitySessions`

### Config / env

- `SSO_MODE=server` in `.env.example` and local `.env`
- `SSO_MODE=server` in `phpunit.xml` for tests
- JWT env vars unchanged (`JWT_*`); package reads them via `config/sso.php`
- Removed JWT bindings from `AppServiceProvider` (package handles them)
- Removed manual `routes/oauth.php` load from `bootstrap/app.php`

### baaboo-sso tests updated

- OAuth tests use `sso.*` config and package `SsoDeviceId`
- Unit JWT tests point at `Company\Sso\Core\Jwt\*`

---

## 3. Client starter (`sso-starter`)

### Scaffolded

- Laravel 13 at `c:\laragon\www\sso-starter` (default Laravel; Livewire starter kit was unavailable via Composer)

### Added

- `company/sso` + Livewire 4 via path repo
- Routes: `/` (home), `/dashboard` (`sso.auth`), `/settings` (Livewire)
- `App\Livewire\ProjectSettings` — form outputs copy-paste `.env` block (no disk write)
- `README.md`, `.env.example` with `SSO_*` vars

---

## 4. Verification commands

```bash
# Package
cd c:\laragon\www\sso-composer
vendor\bin\pest --compact
vendor\bin\phpstan analyse

# IdP
cd c:\laragon\www\baaboo-sso
php artisan test --compact tests\Feature\OAuthSsoFlowTest.php tests\Feature\OAuthSessionEndRevocationTest.php tests\Feature\DeviceScopedSsoSessionTest.php
php artisan route:list --path=oauth
php artisan route:list --path=jwks

# Client starter
cd c:\laragon\www\sso-starter
composer install
# then configure .env and visit /login
```

### Last known results

| Check | Result |
|-------|--------|
| Package Pest | **14 passed** |
| Package PHPStan | **No errors** |
| baaboo-sso OAuth tests | **13 passed** |
| baaboo-sso server routes | `/oauth/token`, `/oauth/heartbeat`, `/oauth/session/end`, `/jwks`, `/.well-known/jwks.json` |
| baaboo-sso authorize | Still `oauth.authorize` in `web.php` (Fortify session) |

---

## 5. Manual verification checklist

1. **`baaboo-sso/.env`** has `SSO_MODE=server` in every environment (dev/staging/prod).
2. **`sso-starter/.env`** — copy from `.env.example`, fill `SSO_*` from IdP admin, set `SSO_MODE=client`.
3. **End-to-end flow** — IdP running → starter `/login` → authorize → `/oauth/callback` → `/dashboard`.
4. **`config/jwt.php`** still exists in baaboo-sso but is unused; package uses `config/sso.php`. Safe to remove or alias later.
5. **Publish config** (optional): `php artisan vendor:publish --tag=sso-config` in host apps.

---

## 6. Repo layout (sibling structure)

```
c:\laragon\www\
├── sso-composer/     ← company/sso package (this repo)
├── baaboo-sso/       ← IdP (server mode)
└── sso-starter/      ← client RP template (client mode)
```
