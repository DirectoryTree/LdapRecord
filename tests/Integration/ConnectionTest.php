<?php

namespace LdapRecord\Tests\Integration;

class ConnectionTest extends TestCase
{
    public function test_connect()
    {
        $conn = $this->makeConnection();

        $conn->connect();

        $this->assertTrue($conn->isConnected());
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

        $conn->auth()->attempt('cn=admin,dc=local,dc=com', 'secret');

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
