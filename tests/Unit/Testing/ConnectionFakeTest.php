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
}

class ExtendedLdapFake extends LdapFake {}
