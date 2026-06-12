<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthProjectResolver;
use Company\Sso\Core\Data\OAuthAuthorizeContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ResolveOAuthAuthorizeContext
{
    public function __construct(private readonly OAuthProjectResolver $projectResolver) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function execute(array $query): OAuthAuthorizeContext
    {
        $validator = Validator::make($query, [
            'redirect_uri' => ['required', 'string', 'url', 'max:2048'],
            'project_id' => ['required', 'string'],
            'prompt' => ['nullable', 'string', 'in:login'],
        ]);

        $data = $validator->validate();

        $project = $this->projectResolver->findBySlug($data['project_id']);

        if ($project === null) {
            throw ValidationException::withMessages([
                'project_id' => ['Unknown project.'],
            ]);
        }

        if (! $project->isActive) {
            throw ValidationException::withMessages([
                'project_id' => ['This project cannot accept SSO sign-ins right now.'],
            ]);
        }

        if (! $project->ssoClientConfigured()) {
            throw ValidationException::withMessages([
                'project_id' => ['OAuth client is not configured for this project.'],
            ]);
        }

        if ($data['redirect_uri'] !== $project->redirectUri()) {
            throw ValidationException::withMessages([
                'redirect_uri' => ['redirect_uri does not match this client registration.'],
            ]);
        }

        return new OAuthAuthorizeContext(
            project: $project,
            redirectUri: $project->redirectUri(),
            forceInteractiveLogin: ($data['prompt'] ?? null) === 'login',
        );
    }
}
