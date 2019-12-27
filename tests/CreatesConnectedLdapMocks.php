<?php

namespace LdapRecord\Tests;

use Mockery as m;
use LdapRecord\LdapInterface;

trait CreatesConnectedLdapMocks
{
    protected function newConnectedLdapMock()
    {
        $ldap = m::mock(LdapInterface::class);
        $ldap->shouldReceive('connect')->once();
        $ldap->shouldReceive('setOptions')->once();

        return $ldap;
    }
}
