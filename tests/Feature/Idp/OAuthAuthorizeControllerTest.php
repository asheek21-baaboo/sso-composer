<?php

use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Core\Data\OAuthUser;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthAuthorizationCodeStore;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthProjectResolver;
use Company\Sso\Tests\Feature\Server\InMemoryOAuthUserResolver;
use Company\Sso\Tests\Feature\Server\RecordingOAuthAuditLogger;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/login', fn () => 'login')->name('login');

    $this->projectResolver = new InMemoryOAuthProjectResolver;
    $this->userResolver = new InMemoryOAuthUserResolver;
    $this->codeStore = new InMemoryOAuthAuthorizationCodeStore;

    $this->project = new OAuthProject(
        id: 1,
        slug: 'demo',
        url: 'https://client.test',
        clientId: '00000000-0000-4000-8000-000000000001',
        clientSecretHash: hash('sha256', 'secret'),
        isActive: true,
        ssoProvisionsUsers: false,
    );

    $this->projectResolver->projects[1] = $this->project;
    $this->userResolver->users[7] = new OAuthUser(
        id: 7,
        email: 'user@example.test',
        globalRole: 'staff',
        isActive: true,
        projectRole: 'viewer',
    );
    $this->userResolver->access['7|1'] = true;

    $this->app->instance(InMemoryOAuthProjectResolver::class, $this->projectResolver);
    $this->app->instance(InMemoryOAuthUserResolver::class, $this->userResolver);
    $this->app->instance(InMemoryOAuthAuthorizationCodeStore::class, $this->codeStore);

    $this->app->bind(
        \Company\Sso\Core\Contracts\OAuthProjectResolver::class,
        fn () => $this->projectResolver,
    );
    $this->app->bind(
        \Company\Sso\Core\Contracts\OAuthUserResolver::class,
        fn () => $this->userResolver,
    );
    $this->app->bind(
        \Company\Sso\Core\Contracts\OAuthAuthorizationCodeStore::class,
        fn () => $this->codeStore,
    );
    $this->app->bind(
        \Company\Sso\Core\Contracts\OAuthAuditLogger::class,
        RecordingOAuthAuditLogger::class,
    );
    $this->app->bind(
        \Company\Sso\Core\Contracts\OAuthSessionStore::class,
        \Company\Sso\Tests\Feature\Server\InMemoryOAuthSessionStore::class,
    );
});

it('redirects guests to login and stores oauth resume query', function (): void {
    $response = $this->get('/oauth/authorize?'.http_build_query([
        'project_id' => 'demo',
        'redirect_uri' => 'https://client.test/oauth/callback',
        'response_type' => 'code',
    ]));

    $response->assertRedirect(route('login'));
});

it('issues an authorization code redirect for authenticated users', function (): void {
    $response = $this->actingAs(new GenericUser(['id' => 7]), 'web')->get('/oauth/authorize?'.http_build_query([
        'project_id' => 'demo',
        'redirect_uri' => 'https://client.test/oauth/callback',
        'response_type' => 'code',
    ]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://client.test/oauth/callback?code=');
    expect($this->codeStore->codes)->toHaveCount(1);
});

it('renders oauth validation errors', function (): void {
    $response = $this->get('/oauth/authorize?project_id=missing');

    $response->assertOk();
    $response->assertSee('OAuth authorization failed');
});
