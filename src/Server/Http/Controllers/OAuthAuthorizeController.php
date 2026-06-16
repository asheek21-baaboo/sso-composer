<?php

namespace Company\Sso\Server\Http\Controllers;

use Company\Sso\Core\Contracts\OAuthUserResolver;
use Company\Sso\Core\Support\SsoDeviceId;
use Company\Sso\Server\Actions\IssueOAuthAuthorizationCodeRedirect;
use Company\Sso\Server\Actions\ResolveOAuthAuthorizeContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class OAuthAuthorizeController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveOAuthAuthorizeContext $resolveOAuthAuthorizeContext,
        IssueOAuthAuthorizationCodeRedirect $issueOAuthAuthorizationCodeRedirect,
        OAuthUserResolver $oauthUserResolver,
    ): RedirectResponse|View {
        try {
            $context = $resolveOAuthAuthorizeContext->execute($request->query());

            if (! Auth::check()) {
                $this->storeResumeQuery($request, $context->redirectUri, $context->project->slug);

                return redirect()->guest(route((string) config('sso.routes.login_route_name', 'login')));
            }

            $oauthUser = $oauthUserResolver->findById(Auth::id());

            if ($oauthUser === null || ! $oauthUser->isActive) {
                abort(403, 'You are not allowed to sign in.');
            }

            if (! $oauthUserResolver->mayAccessProject($oauthUser, $context->project)) {
                abort(403, 'You do not have access to this application.');
            }

            $deviceId = $request->attributes->get('sso.device_id');
            if (! is_string($deviceId) || ! SsoDeviceId::isValidUuid($deviceId)) {
                $deviceId = SsoDeviceId::resolve($request);
            }

            if ($context->forceInteractiveLogin) {
                Auth::logout();
                $this->storeResumeQuery($request, $context->redirectUri, $context->project->slug);

                $status = str_replace(
                    ':project',
                    $context->project->slug,
                    (string) config('sso.session.interactive_login_status', 'Sign in again to continue to :project.'),
                );

                return redirect()
                    ->guest(route((string) config('sso.routes.login_route_name', 'login')))
                    ->with('status', $status);
            }

            $target = $issueOAuthAuthorizationCodeRedirect->execute(
                $oauthUser,
                $context->project,
                $context->redirectUri,
                $deviceId,
                $request,
            );

            return redirect()->away($target);
        } catch (ValidationException $e) {
            return view((string) config('sso.views.oauth_errors', 'sso::oauth.errors'), [
                'errors' => $e->errors(),
            ]);
        }
    }

    private function storeResumeQuery(Request $request, string $redirectUri, string $projectSlug): void
    {
        $request->session()->put((string) config('sso.session.oauth_resume_key', 'sso.oauth.resume_query'), [
            'redirect_uri' => $redirectUri,
            'project_id' => $projectSlug,
        ]);
    }
}
