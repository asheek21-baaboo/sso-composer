<?php

namespace Company\Sso\Server\Http\Controllers;

use Company\Sso\Server\Actions\EndOAuthAccessSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class OAuthSessionEndController extends Controller
{
    public function __invoke(Request $request, EndOAuthAccessSession $endOAuthAccessSession): Response
    {
        $jwt = trim((string) ($request->bearerToken() ?? ''));

        if ($jwt === '') {
            abort(response()->json(['message' => 'Unauthorized.'], 401));
        }

        $endOAuthAccessSession->execute($jwt, $request);

        return response()->noContent();
    }
}
