<?php

namespace Company\Sso\Tests\Feature\Server;

use Company\Sso\Tests\ServerTestCase;

class OAuthAuthorizeServerTestCase extends ServerTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set([
            'sso.routes.authorize_register' => true,
            'sso.routes.login_route_name' => 'login',
        ]);
    }
}
