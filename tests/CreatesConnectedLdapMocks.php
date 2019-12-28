<?php

namespace LdapRecord\Tests;

use Mockery as m;
use LdapRecord\Ldap;

trait CreatesConnectedLdapMocks
{
    protected function newConnectedLdapMock()
    {
        $ldap = m::mock(Ldap::class);
        $ldap->shouldReceive('connect')->once();
        $ldap->shouldReceive('setOptions')->once();

        return $ldap;
    }
}
