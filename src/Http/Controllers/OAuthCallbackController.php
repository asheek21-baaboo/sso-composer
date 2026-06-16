<?php

namespace Company\Sso\Http\Controllers;

use Company\Sso\Actions\ExchangeCodeAndStoreToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class OAuthCallbackController extends Controller
{
    public function __invoke(Request $request, ExchangeCodeAndStoreToken $exchangeCodeAndStoreToken): RedirectResponse
    {
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return redirect()
                ->route((string) config('sso.routes.login_route_name', 'sso.login'))
                ->with('error', 'Missing authorization code.');
        }

        return $exchangeCodeAndStoreToken->execute($code, $request);
    }
}
