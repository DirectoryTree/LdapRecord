<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Connection;

trait CreatesTestConnection
{
    protected function makeConnection(array $config = []): Connection
    {
        return new Connection(array_merge([
            'hosts' => ['localhost'],
            'base_dn' => 'dc=local,dc=com',
            'username' => 'cn=admin,dc=local,dc=com',
            'password' => 'secret',
            'use_ssl' => true,
        ], $config));
    }
}
