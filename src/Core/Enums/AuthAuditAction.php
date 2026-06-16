<?php

namespace Company\Sso\Core\Enums;

enum AuthAuditAction: string
{
    case Login = 'login';
    case LoginFailed = 'login_failed';
    case TokenIssued = 'token_issued';
    case TokenRevoked = 'token_revoked';
}
