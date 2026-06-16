<?php

namespace Company\Sso\Tests;

use Company\Sso\SsoServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SsoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
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
