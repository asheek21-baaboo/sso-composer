<?php

namespace Company\Sso\Tests;

class ClientTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set([
            'sso.mode' => 'client',
            'sso.idp_url' => 'https://idp.test',
            'sso.project_id' => 'my-app',
            'sso.client_id' => '00000000-0000-4000-8000-000000000001',
            'sso.client_secret' => 'secret',
            'sso.app_url' => 'https://app.test',
            'sso.home_route' => 'home',
            'sso.jwks_cache_seconds' => 3600,
        ]);
    }
}
