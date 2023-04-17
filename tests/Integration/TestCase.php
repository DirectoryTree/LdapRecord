<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Connection;
use LdapRecord\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function makeConnection(array $params = []): Connection
    {
        return new Connection(array_merge([
            'hosts'    => ['localhost'],
            'base_dn'  => 'dc=local,dc=com',
            'username' => 'cn=admin,dc=local,dc=com',
            'password' => 'secret',
            'use_ssl'  => true,
        ], $params));
    }
}
