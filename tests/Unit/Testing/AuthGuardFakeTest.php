<?php

namespace LdapRecord\Tests\Unit\Testing;

use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Testing\AuthGuardFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class AuthGuardFakeTest extends TestCase
{
    public function test_bind_as_configured_user_always_returns_null()
    {
        $guard = new AuthGuardFake(new LdapFake, new DomainConfiguration);

        $this->assertNull($guard->bindAsConfiguredUser());
    }
}
