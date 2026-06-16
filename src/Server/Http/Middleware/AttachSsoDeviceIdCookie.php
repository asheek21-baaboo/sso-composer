<?php

namespace Company\Sso\Server\Http\Middleware;

use Closure;
use Company\Sso\Core\Support\SsoDeviceId;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttachSsoDeviceIdCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = SsoDeviceId::resolve($request);
        $request->attributes->set('sso.device_id', $deviceId);

        $response = $next($request);
        $response->headers->setCookie(SsoDeviceId::makeCookie($deviceId));

        return $response;
    }
}
