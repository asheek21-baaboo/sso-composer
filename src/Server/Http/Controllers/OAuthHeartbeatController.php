<?php

namespace Company\Sso\Server\Http\Controllers;

use Company\Sso\Server\Actions\TouchOAuthHeartbeat;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

final class OAuthHeartbeatController extends Controller
{
    public function __invoke(Request $request, TouchOAuthHeartbeat $touchOAuthHeartbeat): Response
    {
        $jwt = trim((string) ($request->bearerToken() ?? ''));

        if ($jwt === '') {
            abort(response()->json(['message' => 'Unauthorized.'], 401));
        }

        $touchOAuthHeartbeat->execute($jwt);

        return response()->noContent();
    }
}
