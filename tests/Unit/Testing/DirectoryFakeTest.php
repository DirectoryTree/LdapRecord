<?php

namespace LdapRecord\Tests\Unit\Testing;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\ContainerException;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class DirectoryFakeTest extends TestCase
{
    public function test_setup_throws_exception_when_no_connection_has_been_configured()
    {
        $this->expectException(ContainerException::class);

        DirectoryFake::setup();
    }

    public function test_setup_creates_connected_fake_connection_and_ldap_instance()
    {
        Container::addConnection(new Connection);

        $fake = DirectoryFake::setup();

        $this->assertTrue($fake->isConnected());

        $this->assertInstanceOf(ConnectionFake::class, $fake);
        $this->assertInstanceOf(LdapFake::class, $fake->getLdapConnection());
    }

    public function test_tear_down_flushes_container()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup();

        $this->assertCount(1, Container::getConnections());

        DirectoryFake::tearDown();

        $this->assertCount(0, Container::getConnections());
    }
}
