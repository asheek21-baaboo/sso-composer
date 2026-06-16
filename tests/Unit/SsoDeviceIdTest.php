<?php

use Company\Sso\Support\SsoDeviceId;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

test('sso device id resolves from cookie or generates uuid', function (): void {
    $uuid = (string) Str::uuid();
    $request = Request::create('/', 'GET', [], [SsoDeviceId::COOKIE_NAME => $uuid]);

    expect(SsoDeviceId::resolve($request))->toBe($uuid);
    expect(SsoDeviceId::isValidUuid($uuid))->toBeTrue();
    expect(SsoDeviceId::isValidUuid('not-a-uuid'))->toBeFalse();

    $fresh = Request::create('/');
    $generated = SsoDeviceId::resolve($fresh);
    expect(SsoDeviceId::isValidUuid($generated))->toBeTrue();
});

test('sso device id cookie is httpOnly', function (): void {
    $cookie = SsoDeviceId::makeCookie((string) Str::uuid());

    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getName())->toBe('sso_device_id');
});
