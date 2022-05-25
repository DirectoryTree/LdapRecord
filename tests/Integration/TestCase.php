<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Connection;
use LdapRecord\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function makeConnection()
    {
        return new Connection([
            'hosts' => ['127.0.0.1'],
            'base_dn' => 'dc=local,dc=com',
            'username' => 'cn=admin,dc=local,dc=com',
            'password' => 'secret',
        ]);
    }
}
