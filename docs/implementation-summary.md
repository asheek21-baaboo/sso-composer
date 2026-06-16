# Work Summary — `company/sso` Package

Verification-oriented summary of what was built and integrated.

---

## Intended architecture

**Purpose:** Let internal devs ship Laravel apps that login via **`baaboo-sso`** without installing the IdP codebase.

| System | Who uses it | Responsibility |
|--------|-------------|----------------|
| **`baaboo-sso`** | Platform / SSO team | IdP: users, projects, authorize, token issue, JWKS |
| **`company/sso` (this package)** | Every internal app dev | Login redirect, callback, token exchange, JWT cookie, `sso.auth` middleware |
| **`sso-starter`** | New projects | Cloneable minimal Laravel + this package |

Devs configure `SSO_BASE_URL`, `SSO_PROJECT_ID`, `SSO_CLIENT_ID`, and `SSO_CLIENT_SECRET`.

---

## Package structure (current)

```
src/
├── Actions/              RedirectToIdpLogin, ExchangeCodeAndStoreToken, LogoutSsoSession
├── Http/
│   ├── Controllers/      SsoLoginController, OAuthCallbackController
│   └── Middleware/     AuthenticateSso
├── Jwt/                  RemoteJwksVerifier
├── Support/              OAuthUrls, SsoDeviceId, AccessTokenCookie
├── Facades/              SsoUser
├── SsoAuthenticatedUser.php
└── SsoServiceProvider.php
config/sso.php
routes/web.php            /login, /oauth/callback
```

No `SSO_MODE`, no `Client/` or `Server/` layers, no IdP contracts or token-issuing code.

---

## Verification commands

```bash
cd sso-composer
vendor/bin/pest --compact
vendor/bin/phpstan analyse
```

### Last known results

| Check | Result |
|-------|--------|
| Package Pest | **8 passed** |
| Package PHPStan | clean |

---

## Related repos

| Repo | Role |
|------|------|
| [sso-composer](https://github.com/asheek21-baaboo/sso-composer) | This package (`company/sso`) |
| `baaboo-sso` | IdP (server OAuth stays in this app) |
| `sso-starter` | Minimal client template |

---

## Historical note

An earlier build included IdP/server endpoints and a dual `SSO_MODE` split inside this package. That was removed so the package is **RP-only**. `baaboo-sso` should own token/JWKS/heartbeat endpoints directly (not via this package).
