<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;

class LiveDirectoryTest extends TestCase
{
    public function test()
    {
        Container::addConnection(
            new Connection([
                'hosts' => ['127.0.0.1'],
                'base_dn' => 'dc=local,dc=com',
                'username' => 'cn=admin,dc=local,dc=com',
                'password' => 'secret',
            ])
        );

        $this->assertCount(2, Entry::all());
    }
}
