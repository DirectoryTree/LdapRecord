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
    public function testSetupThrowsExceptionWhenNoConnectionHasBeenConfigured()
    {
        $this->expectException(ContainerException::class);

        DirectoryFake::setup();
    }

    public function testSetupCreatesConnectedFakeConnectionAndLdapInstance()
    {
        Container::addConnection(new Connection);

        $fake = DirectoryFake::setup();

        $this->assertTrue($fake->isConnected());

        $this->assertInstanceOf(ConnectionFake::class, $fake);
        $this->assertInstanceOf(LdapFake::class, $fake->getLdapConnection());
    }
}
