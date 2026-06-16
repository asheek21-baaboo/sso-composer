<?php

use Company\Sso\Core\Data\OAuthProject;
use Company\Sso\Server\Concerns\InteractsWithOAuthClient;
use Company\Sso\Server\Contracts\OAuthClientProject;

final class StubOAuthClientProject implements OAuthClientProject
{
    use InteractsWithOAuthClient;

    public function __construct(
        private readonly string $slug,
        private readonly string $url,
        private readonly ?string $clientId,
        private readonly ?string $clientSecretHash,
        private readonly bool $active = true,
        private readonly bool $provisionsUsers = false,
    ) {}

    public function oauthProjectSlug(): string
    {
        return $this->slug;
    }

    public function oauthClientAppUrl(): string
    {
        return $this->url;
    }

    public function oauthClientId(): ?string
    {
        return $this->clientId;
    }

    public function oauthClientSecretHash(): ?string
    {
        return $this->clientSecretHash;
    }

    public function oauthProjectIsActive(): bool
    {
        return $this->active;
    }

    public function oauthProvisionsUsers(): bool
    {
        return $this->provisionsUsers;
    }
}

it('builds redirect and authorize urls from oauth client fields', function (): void {
    config(['app.url' => 'https://idp.test']);

    $project = new StubOAuthClientProject(
        slug: 'my-app',
        url: 'https://app.test/',
        clientId: 'cid',
        clientSecretHash: 'hash',
    );

    expect($project->redirectUri())->toBe('https://app.test/oauth/callback')
        ->and($project->authorizeUri())->toContain('https://idp.test/oauth/authorize')
        ->and($project->authorizeUri())->toContain('project_id=my-app')
        ->and($project->ssoClientConfigured())->toBeTrue()
        ->and($project->canVisit())->toBeTrue();
});

it('reports unconfigured oauth client when credentials are missing', function (): void {
    $project = new StubOAuthClientProject(
        slug: 'my-app',
        url: 'https://app.test',
        clientId: null,
        clientSecretHash: 'hash',
    );

    expect($project->ssoClientConfigured())->toBeFalse()
        ->and($project->canVisit())->toBeFalse();
});
