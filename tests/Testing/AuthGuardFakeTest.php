<?php

namespace LdapRecord\Tests\Testing;

use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Tests\TestCase;
use LdapRecord\Testing\AuthGuardFake;
use LdapRecord\Testing\LdapFake;

class AuthGuardFakeTest extends TestCase
{
    public function testBindAsConfiguredUserAlwaysReturnsTrue()
    {
        $guard = new AuthGuardFake(new LdapFake(), new DomainConfiguration());

        $this->assertTrue($guard->bindAsConfiguredUser());
    }
}
