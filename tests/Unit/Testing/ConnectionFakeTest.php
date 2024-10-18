<?php

namespace LdapRecord\Testing;

use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;

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
        $fake = ConnectionFake::make([], ExtendedLdapFake::class);

        $this->assertInstanceOf(ExtendedLdapFake::class, $fake->getLdapConnection());
    }

    public function testActingAsWithModel()
    {
        $fake = ConnectionFake::make();

        $user = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=local,dc=com',
        ]);

        $fake->actingAs($user);

        $ldap = $fake->getLdapConnection();

        $this->assertTrue($ldap->hasExpectations('bind'));

        $this->assertTrue($fake->auth()->attempt($user->getDn(), 'secret', $stayBound = true));
    }

    public function testActingAsWithDn()
    {
        $fake = ConnectionFake::make();

        $fake->actingAs('cn=John Doe,dc=local,dc=com');

        $ldap = $fake->getLdapConnection();

        $this->assertTrue($ldap->hasExpectations('bind'));

        $this->assertTrue($fake->auth()->attempt('cn=John Doe,dc=local,dc=com', 'secret', $stayBound = true));
    }
}

class ExtendedLdapFake extends LdapFake
{
}
