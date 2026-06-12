<?php

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Contracts\OAuthAuthorizationCodeStore;
use Company\Sso\Core\Contracts\OAuthProjectResolver;
use Company\Sso\Core\Contracts\OAuthSessionStore;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Company\Sso\Core\Enums\AuthAuditAction;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthAuthorizationCodeStore;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthProjectResolver;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthSessionStore;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthUserResolver;
use Company\Sso\Tests\Feature\Server\RecordingOAuthAuditLogger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses()->group('server');

beforeEach(function (): void {
    $privatePem = (string) file_get_contents(__DIR__.'/../../Fixtures/jwt/rs256-private.pem');
    $publicPem = (string) file_get_contents(__DIR__.'/../../Fixtures/jwt/rs256-public.pem');

    config([
        'sso.mode' => 'server',
        'sso.private_key_pem' => $privatePem,
        'sso.public_key_pem' => $publicPem,
        'sso.key_id' => 'test-kid',
        'sso.issuer' => 'https://idp.test',
        'sso.ttl_seconds' => 900,
        'sso.authorization_code_ttl_seconds' => 60,
        'app.url' => 'https://idp.test',
    ]);

    $this->projectResolver = new InMemoryOAuthProjectResolver;
    $this->userResolver = new InMemoryOAuthUserResolver;
    $this->codeStore = new InMemoryOAuthAuthorizationCodeStore;
    $this->sessionStore = new InMemoryOAuthSessionStore;
    $this->auditLogger = new RecordingOAuthAuditLogger;

    $this->app->instance(OAuthProjectResolver::class, $this->projectResolver);
    $this->app->instance(OAuthUserResolver::class, $this->userResolver);
    $this->app->instance(OAuthAuthorizationCodeStore::class, $this->codeStore);
    $this->app->instance(OAuthSessionStore::class, $this->sessionStore);
    $this->app->instance(OAuthAuditLogger::class, $this->auditLogger);
});

function seedOAuthExchangeFixtures(
    object $test,
    bool $ssoProvisionsUsers = true,
): array {
    $plainSecret = str_repeat('p', 64);
    $clientId = (string) Str::uuid();
    $deviceId = (string) Str::uuid();
    $redirect = 'https://client.test/oauth/callback';

    $project = new OAuthProject(
        id: 1,
        slug: 'hr-portal',
        url: 'https://client.test',
        clientId: $clientId,
        clientSecretHash: Hash::make($plainSecret),
        isActive: true,
        ssoProvisionsUsers: $ssoProvisionsUsers,
    );

    $user = new OAuthUser(
        id: 42,
        email: 'user@company.com',
        globalRole: 'staff',
        isActive: true,
        projectRole: 'viewer',
    );

    $test->projectResolver->projects[1] = $project;
    $test->userResolver->users[42] = $user;
    $test->userResolver->access['42|1'] = true;

    $plainCode = Str::random(48);
    $test->codeStore->create(
        userId: 42,
        projectId: 1,
        deviceId: $deviceId,
        redirectUriSnapshot: $redirect,
        codeHash: hash('sha256', $plainCode),
        expiresAt: now()->addMinute(),
    );

    return compact('plainSecret', 'clientId', 'deviceId', 'redirect', 'plainCode', 'project', 'user');
}

test('token exchange rejects bad code with 422', function (): void {
    seedOAuthExchangeFixtures($this);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => 'not-a-real-code',
        'redirect_uri' => 'https://client.test/oauth/callback',
        'client_id' => (string) Str::uuid(),
        'client_secret' => 'wrong',
    ]);

    $response->assertStatus(422)->assertJsonStructure(['message']);
    expect($this->auditLogger->entries)->not->toBeEmpty()
        ->and($this->auditLogger->entries[0]['action'])->toBe(AuthAuditAction::LoginFailed);
    expect($this->sessionStore->sessions)->toBeEmpty();
});

test('token exchange mints valid jwt with correct claims', function (): void {
    $fixtures = seedOAuthExchangeFixtures($this);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $fixtures['plainCode'],
        'redirect_uri' => $fixtures['redirect'],
        'client_id' => $fixtures['clientId'],
        'client_secret' => $fixtures['plainSecret'],
    ]);

    $response->assertOk()->assertJsonStructure(['access_token', 'expires_in']);

    JWT::$timestamp = time();

    $decoded = JWT::decode(
        (string) $response->json('access_token'),
        new Key((string) config('sso.public_key_pem'), 'RS256'),
    );

    expect($decoded->sub ?? null)->toBe('42')
        ->and($decoded->project_id ?? null)->toBe('hr-portal')
        ->and($decoded->createUser ?? null)->toBeTrue()
        ->and($decoded->email ?? null)->toBe('user@company.com');

    JWT::$timestamp = null;

    expect($this->codeStore->codes)->toBeEmpty();
    expect($this->sessionStore->sessions)->not->toBeEmpty();
});

test('heartbeat returns 204 with valid token', function (): void {
    $fixtures = seedOAuthExchangeFixtures($this);

    $exchange = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $fixtures['plainCode'],
        'redirect_uri' => $fixtures['redirect'],
        'client_id' => $fixtures['clientId'],
        'client_secret' => $fixtures['plainSecret'],
    ]);

    $token = (string) $exchange->json('access_token');

    $this->post('/oauth/heartbeat', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(204);

    $this->post('/oauth/heartbeat', [], [
        'Authorization' => 'Bearer invalid',
    ])->assertStatus(401);
});

test('session end returns 204 and closes session', function (): void {
    $fixtures = seedOAuthExchangeFixtures($this);

    $exchange = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $fixtures['plainCode'],
        'redirect_uri' => $fixtures['redirect'],
        'client_id' => $fixtures['clientId'],
        'client_secret' => $fixtures['plainSecret'],
    ]);

    $token = (string) $exchange->json('access_token');

    $this->post('/oauth/session/end', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(204);

    $session = array_values($this->sessionStore->sessions)[0];
    expect($session['session_end'])->not->toBeNull();
});

test('createUser claim reflects OAuthProject ssoProvisionsUsers', function (): void {
    $fixtures = seedOAuthExchangeFixtures($this, ssoProvisionsUsers: false);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'code' => $fixtures['plainCode'],
        'redirect_uri' => $fixtures['redirect'],
        'client_id' => $fixtures['clientId'],
        'client_secret' => $fixtures['plainSecret'],
    ]);

    JWT::$timestamp = time();

    $decoded = JWT::decode(
        (string) $response->json('access_token'),
        new Key((string) config('sso.public_key_pem'), 'RS256'),
    );

    expect($decoded->createUser ?? null)->toBeFalse();

    JWT::$timestamp = null;
});
