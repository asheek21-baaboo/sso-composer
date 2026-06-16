<?php

namespace Company\Sso\Facades;

use Company\Sso\SsoAuthenticatedUser;
use Illuminate\Support\Facades\Facade;

/**
 * @method static int id()
 * @method static string email()
 * @method static string projectRole()
 * @method static string globalRole()
 * @method static bool createUser()
 * @method static \stdClass claims()
 *
 * @see SsoAuthenticatedUser
 */
final class SsoUser extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SsoAuthenticatedUser::class;
    }
}
