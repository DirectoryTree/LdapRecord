<?php

namespace LdapRecord\Tests\Unit\Testing;

use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Testing\AuthGuardFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class AuthGuardFakeTest extends TestCase
{
    public function testBindAsConfiguredUserAlwaysReturnsTrue()
    {
        $guard = new AuthGuardFake(new LdapFake(), new DomainConfiguration());

        $this->assertTrue($guard->bindAsConfiguredUser());
    }
}
