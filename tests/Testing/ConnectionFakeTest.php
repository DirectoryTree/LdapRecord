<?php

namespace LdapRecord\Testing;

use LdapRecord\Tests\TestCase;
use LdapRecord\Testing\ConnectionFake;

class ConnectionFakeTest extends TestCase
{
    public function testMake()
    {
        $this->assertInstanceOf(ConnectionFake::class, ConnectionFake::make());
    }

    public function testMakeWithConfig()
    {
        $fake = $fake = ConnectionFake::make([
            'hosts' => ['foo', 'bar'],
            'use_tls' => true,
        ]);
        
        $config = $fake->getConfiguration();

        $this->assertEquals(['foo', 'bar'], $config->get('hosts'));
        $this->assertTrue($config->get('use_tls'));
    }

    public function testMakeWithCustomLdapFake()
    {
        $fake =  ConnectionFake::make([], ExtendedLdapFake::class);

        $this->assertInstanceOf(ExtendedLdapFake::class, $fake->getLdapConnection());
    }
}

class ExtendedLdapFake extends LdapFake
{
}
