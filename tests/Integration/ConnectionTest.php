<?php

namespace LdapRecord\Tests\Integration;

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
}
