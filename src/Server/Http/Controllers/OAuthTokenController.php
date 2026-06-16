<?php

namespace Company\Sso\Server\Http\Controllers;

use Company\Sso\Server\Actions\ExchangeAuthorizationCodeForAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class OAuthTokenController extends Controller
{
    public function __invoke(Request $request, ExchangeAuthorizationCodeForAccessToken $exchange): JsonResponse
    {
        $validated = $request->validate([
            'grant_type' => ['required', 'string'],
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'string', 'url', 'max:2048'],
            'client_id' => ['required', 'string', 'uuid'],
            'client_secret' => ['required', 'string'],
        ]);

        return response()->json($exchange->execute($validated, $request));
    }
}
