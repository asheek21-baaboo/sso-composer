<?php

namespace Company\Sso\Tests;

class ServerTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $privatePem = (string) file_get_contents(__DIR__.'/Fixtures/jwt/rs256-private.pem');
        $publicPem = (string) file_get_contents(__DIR__.'/Fixtures/jwt/rs256-public.pem');

        $app['config']->set([
            'sso.mode' => 'server',
            'sso.private_key_pem' => $privatePem,
            'sso.public_key_pem' => $publicPem,
            'sso.key_id' => 'test-kid',
            'sso.issuer' => 'https://idp.test',
            'sso.ttl_seconds' => 900,
            'sso.authorization_code_ttl_seconds' => 60,
            'app.url' => 'https://idp.test',
        ]);
    }
}
