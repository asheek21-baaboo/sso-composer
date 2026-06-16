<?php

namespace Company\Sso\Tests;

class ClientTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        putenv('SSO_MODE=client');
        $_ENV['SSO_MODE'] = 'client';

        $app['config']->set([
            'sso.base_url' => 'https://idp.test',
            'sso.project_id' => 'my-app',
            'sso.client_id' => '00000000-0000-4000-8000-000000000001',
            'sso.client_secret' => 'secret',
            'sso.app_url' => 'https://app.test',
            'sso.home_route' => 'home',
            'sso.jwks_cache_seconds' => 3600,
        ]);
    }
}
