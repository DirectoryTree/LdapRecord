<?php

namespace LdapRecord\Tests;

use LdapRecord\Connection;

class LiveLdapTest extends TestCase
{
    /** @var Connection */
    protected $conn;

    protected function setUp(): void
    {
        $this->conn = new Connection([
            'hosts' => ['52.87.186.93'],
            'base_dn' => 'dc=example,dc=com',
            'username' => 'cn=read-only-admin,dc=example,dc=com',
            'password' => 'password',
        ]);

        $this->conn->connect();
    }

    public function test_connectivity()
    {
        $this->assertTrue($this->conn->isConnected());
    }

    public function test_search()
    {
        $this->assertCount(23, $this->conn->query()->get());
    }

    public function test_find()
    {
        $admin = $this->conn->query()->find('cn=admin,dc=example,dc=com');

        $this->assertEquals([
            'objectclass' => [
                'count' => 2,
                'simpleSecurityObject',
                'organizationalRole',
            ],
            'objectclass',
            'cn' => [
                'count' => 1,
                'admin',
            ],
            'cn',
            'description' => [
                'count' => 1,
                'LDAP administrator',
            ],
            2 => 'description',
            'count' => 3,
            'dn' => 'cn=admin,dc=example,dc=com',
        ], $admin);
    }

    public function test_in()
    {
        $results = $this->conn->query()->in('ou=mathematicians,{base}')->get();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('uniquemember', $ou = $results[0]);
        $this->assertEquals(5, $ou['uniquemember']['count']);

        unset($ou['uniquemember']['count']);

        $this->assertEquals([
            'uid=euclid,dc=example,dc=com',
            'uid=riemann,dc=example,dc=com',
            'uid=euler,dc=example,dc=com',
            'uid=gauss,dc=example,dc=com',
            'uid=test,dc=example,dc=com',
        ], $ou['uniquemember']);
    }
}
