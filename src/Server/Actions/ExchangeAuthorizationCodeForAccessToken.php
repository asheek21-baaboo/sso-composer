<?php

namespace Company\Sso\Server\Actions;

use Company\Sso\Core\Contracts\OAuthAuditLogger;
use Company\Sso\Core\Contracts\OAuthAuthorizationCodeStore;
use Company\Sso\Core\Contracts\OAuthProjectResolver;
use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Enums\AuthAuditAction;
use Company\Sso\Core\Support\SsoDeviceId;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ExchangeAuthorizationCodeForAccessToken
{
    public function __construct(
        private readonly OAuthAuthorizationCodeStore $authorizationCodeStore,
        private readonly OAuthProjectResolver $projectResolver,
        private readonly OAuthUserResolver $userResolver,
        private readonly StartOrResumeSsoAccessSession $startOrResumeSsoAccessSession,
        private readonly OAuthAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{access_token: string, expires_in: int, token_type: string}
     */
    public function execute(array $input, Request $request): array
    {
        if (($input['grant_type'] ?? null) !== 'authorization_code') {
            $this->deny($request, null, 'Unsupported grant_type.');
        }

        $code = (string) ($input['code'] ?? '');
        $redirectUri = (string) ($input['redirect_uri'] ?? '');
        $clientId = (string) ($input['client_id'] ?? '');
        $clientSecret = (string) ($input['client_secret'] ?? '');

        if ($code === '' || $redirectUri === '' || $clientId === '' || $clientSecret === '') {
            $this->deny($request, null, 'Invalid request.');
        }

        $codeHash = hash('sha256', $code);
        $authCode = $this->authorizationCodeStore->findByCodeHash($codeHash);

        if ($authCode === null) {
            $this->deny($request, null, 'Invalid or expired code.');
        }

        if ($authCode['expires_at'] < new \DateTimeImmutable) {
            $this->authorizationCodeStore->delete($authCode['id']);
            $this->deny($request, null, 'Invalid or expired code.');
        }

        $project = $this->projectResolver->findById($authCode['project_id']);

        if ($project === null) {
            $this->deny($request, null, 'Invalid or expired code.');
        }

        if (! $project->matchesClientId($clientId)) {
            $this->deny($request, $project->id, 'Invalid client credentials.');
        }

        if ($project->clientSecretHash === '' || ! Hash::check($clientSecret, $project->clientSecretHash)) {
            $this->deny($request, $project->id, 'Invalid client credentials.');
        }

        if ($redirectUri !== $authCode['redirect_uri_snapshot']) {
            $this->deny($request, $project->id, 'Invalid redirect_uri.');
        }

        $user = $this->userResolver->findById($authCode['user_id']);

        if ($user === null || ! $user->isActive) {
            $this->authorizationCodeStore->delete($authCode['id']);
            $this->deny($request, $project->id, 'User is not allowed to authenticate.');
        }

        if (! $this->userResolver->mayAccessProject($user, $project)) {
            $this->authorizationCodeStore->delete($authCode['id']);
            $this->deny($request, $project->id, 'User is not allowed to authenticate.');
        }

        $deviceId = is_string($authCode['device_id']) && SsoDeviceId::isValidUuid($authCode['device_id'])
            ? $authCode['device_id']
            : (string) Str::uuid();

        $this->authorizationCodeStore->delete($authCode['id']);

        $tokenPayload = $this->startOrResumeSsoAccessSession->execute($user, $project, $deviceId, $request);

        $this->auditLogger->log(
            AuthAuditAction::TokenIssued,
            $user->id,
            $project->id,
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 4096),
            ['device_id' => $deviceId],
        );

        return $tokenPayload;
    }

    private function deny(Request $request, int|string|null $projectId, string $message): never
    {
        $this->auditLogger->log(
            AuthAuditAction::LoginFailed,
            null,
            $projectId,
            (string) $request->ip(),
            substr((string) $request->userAgent(), 0, 4096),
            ['message' => $message],
        );

        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
