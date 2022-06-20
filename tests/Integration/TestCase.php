<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Connection;
use LdapRecord\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function makeConnection()
    {
        return new Connection([
            'hosts' => ['localhost'],
            'base_dn' => 'dc=local,dc=com',
            'username' => 'cn=admin,dc=local,dc=com',
            'password' => 'secret',
            'use_ssl' => true,
        ]);
    }
}
