<?php

namespace LdapRecord\Tests\Unit;

use LdapRecord\Ldap;
use LdapRecord\LdapInterface;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class LdapTest extends TestCase
{
    public function test_construct_defaults()
    {
        $ldap = new Ldap;

        $this->assertFalse($ldap->isUsingStartTLS());
        $this->assertFalse($ldap->isUsingTLS());
        $this->assertFalse($ldap->isBound());
        $this->assertNull($ldap->getConnection());
        $this->assertEquals($ldap->getProtocol(), LdapInterface::PROTOCOL);
    }

    public function test_host_arrays_are_properly_processed()
    {
        $ldap = new LdapFake;

        $ldap->connect(['dc01', 'dc02'], $port = 500);

        $this->assertEquals('ldap://dc01:500 ldap://dc02:500', $ldap->getHost());
    }

    public function test_host_strings_are_properly_created()
    {
        $ldap = new LdapFake;

        $ldap->connect('dc01', $port = 500, 'foo://');

        $this->assertEquals('foo://dc01:500', $ldap->getHost());
    }

    public function test_get_default_protocol()
    {
        $ldap = new Ldap;

        $this->assertEquals('ldap://', $ldap->getProtocol());
    }

    public function test_get_protocol_tls()
    {
        $ldap = new Ldap;

        $ldap->setTLS();

        $this->assertEquals('ldaps://', $ldap->getProtocol());
    }

    public function test_get_host()
    {
        $ldap = new Ldap;

        $ldap->connect('192.168.1.1');

        $this->assertEquals('ldap://192.168.1.1:389', $ldap->getHost());
    }

    public function test_get_host_is_null_without_connecting()
    {
        $ldap = new Ldap;

        $this->assertNull($ldap->getHost());
    }

    public function test_can_change_passwords()
    {
        $ldap = new Ldap;

        $ldap->setTLS();

        $this->assertTrue($ldap->canChangePasswords());

        $ldap->setTLS(false);

        $this->assertFalse($ldap->canChangePasswords());

        $ldap->setStartTLS();

        $this->assertTrue($ldap->canChangePasswords());
    }

    public function test_set_options()
    {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('setOption')->once()->with(1, 'value')->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(2, 'value')->andReturnTrue(),
        ]);

        $ldap->setOptions([1 => 'value', 2 => 'value']);
    }

    public function test_get_detailed_error_returns_null_when_error_number_is_zero()
    {
        $ldap = m::mock(Ldap::class)->makePartial();

        $ldap->shouldReceive('errNo')->once()->andReturn(0);

        $this->assertNull($ldap->getDetailedError());
    }

    public function test_is_secure_after_binding_with_a_tls_connection()
    {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('bind')->once()->andReturnResponse(),
        ]);

        $ldap->setTLS();

        $this->assertFalse($ldap->isSecure());

        $ldap->bind('foo', 'bar');

        $this->assertTrue($ldap->isSecure());
    }

    public function test_is_secure_after_starting_starttls()
    {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('startTLS')->once()->andReturnTrue(),
        ]);

        $this->assertFalse($ldap->isSecure());

        $ldap->startTLS();

        $this->assertTrue($ldap->isSecure());
    }

    public function test_is_secure_after_starting_starttls_but_failing_bind()
    {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('startTLS')->once()->andReturnTrue(),
            LdapFake::operation('bind')->once()->andReturnResponse(1),
        ]);

        $this->assertFalse($ldap->isSecure());

        $ldap->startTLS();

        $this->assertTrue($ldap->isSecure());

        $ldap->bind('foo', 'bar');

        $this->assertTrue($ldap->isSecure());
    }
}
