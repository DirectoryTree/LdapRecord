<?php

namespace LdapRecord\Testing;

use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;

class ConnectionFakeTest extends TestCase
{
    public function test_make()
    {
        $this->assertInstanceOf(ConnectionFake::class, ConnectionFake::make());
    }

    public function test_make_with_config()
    {
        $fake = $fake = ConnectionFake::make([
            'hosts' => ['foo', 'bar'],
            'use_starttls' => true,
        ]);

        $config = $fake->getConfiguration();

        $this->assertEquals(['foo', 'bar'], $config->get('hosts'));
        $this->assertTrue($config->get('use_starttls'));
    }

    public function test_make_with_custom_ldap_fake()
    {
        $fake = ConnectionFake::make([], ExtendedLdapFake::class);

        $this->assertInstanceOf(ExtendedLdapFake::class, $fake->getLdapConnection());
    }

    public function test_acting_as_with_model()
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

    public function test_acting_as_with_dn()
    {
        $fake = ConnectionFake::make();

        $fake->actingAs('cn=John Doe,dc=local,dc=com');

        $ldap = $fake->getLdapConnection();

        $this->assertTrue($ldap->hasExpectations('bind'));

        $this->assertTrue($fake->auth()->attempt('cn=John Doe,dc=local,dc=com', 'secret', $stayBound = true));
    }

    public function test_replicated_connection_shares_the_fake_without_resetting_it_on_disconnect()
    {
        $fake = ConnectionFake::make();

        $ldap = $fake->getLdapConnection();

        $ldap->connect('localhost', 389);

        $replica = $fake->replicate();

        $this->assertSame($ldap, $replica->getLdapConnection());

        // Disconnecting the replica (as Connection::isolate() does) must
        // not reset the state of the fake shared with the parent.
        $replica->disconnect();

        $this->assertTrue($ldap->isConnected());
    }
}

class ExtendedLdapFake extends LdapFake {}
