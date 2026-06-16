<?php

namespace Company\Sso\Client\Http\Controllers;

use Company\Sso\Client\Actions\RedirectToIdpLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

final class SsoLoginController extends Controller
{
    public function __invoke(RedirectToIdpLogin $redirectToIdpLogin): RedirectResponse
    {
        return $redirectToIdpLogin->execute();
    }
}
