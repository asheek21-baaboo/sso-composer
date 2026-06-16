<?php

use Company\Sso\Tests\ClientTestCase;
use Company\Sso\Tests\ServerTestCase;
use Company\Sso\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(ClientTestCase::class)->in('Feature/Client');
pest()->extend(ServerTestCase::class)->in('Feature/Server');
pest()->extend(\Company\Sso\Tests\Feature\Server\OAuthAuthorizeServerTestCase::class)->in('Feature/Idp');
