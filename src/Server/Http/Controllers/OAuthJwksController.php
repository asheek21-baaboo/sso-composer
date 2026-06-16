<?php

namespace Company\Sso\Server\Http\Controllers;

use Company\Sso\Core\Jwt\JwtKeyLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class OAuthJwksController extends Controller
{
    public function __invoke(JwtKeyLoader $jwtKeyLoader): JsonResponse
    {
        return response()->json([
            'keys' => [
                $jwtKeyLoader->publicJwk(),
            ],
        ]);
    }
}
