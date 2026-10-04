<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Tests\Integration\Concerns\CreatesTestConnection;

class ConnectionTest extends TestCase
{
    use CreatesTestConnection;

    public function test_connect()
    {
        $conn = $this->makeConnection();

        $conn->connect();

        $this->assertTrue($conn->isConnected());
    }

    public function test_replicate()
    {
        $conn = $this->makeConnection();

        $conn->connect();

        $clone = $conn->replicate();

        $this->assertTrue($conn->isConnected());
        $this->assertFalse($clone->isConnected());

        $clone->connect();

        $this->assertTrue($clone->isConnected());
    }

    public function test_disconnect()
    {
        $conn = $this->makeConnection();

        $conn->connect();

        $this->assertTrue($conn->isConnected());

        $conn->disconnect();

        $this->assertFalse($conn->isConnected());
    }

    public function test_auth_reconnects_to_configured_user_after_successful_attempt()
    {
        $conn = $this->makeConnection();

        $this->assertFalse($conn->isConnected());

        $this->assertTrue($conn->auth()->attempt('cn=admin,dc=local,dc=com', 'secret'));

        $this->assertTrue($conn->isConnected());
    }

    public function test_auth_reconnects_to_configured_user_after_failed_attempt()
    {
        $conn = $this->makeConnection();

        $this->assertFalse($conn->isConnected());

        $this->assertFalse($conn->auth()->attempt('foo', 'bar'));

        $this->assertTrue($conn->isConnected());
    }

    public function test_changing_a_users_password_preserves_the_primary_connection_identity()
    {
        $connection = $this->makeConnection();

        Container::addConnection($connection);

        $user = new User([
            'cn' => 'password-change-'.bin2hex(random_bytes(8)),
            'sn' => 'Password Change',
        ]);

        $user->password = 'current-secret';
        $user->save();

        try {
            $identity = ldap_exop_whoami($connection->getLdapConnection()->getConnection());

            $user->changePassword('current-secret', 'new-secret');

            $this->assertEquals($identity, ldap_exop_whoami($connection->getLdapConnection()->getConnection()));
            $this->assertTrue($connection->auth()->attempt($user->getDn(), 'new-secret'));
            $this->assertFalse($connection->auth()->attempt($user->getDn(), 'current-secret'));
        } finally {
            $user->delete();
        }
    }
}
