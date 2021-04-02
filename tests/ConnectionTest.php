<?php

namespace LdapRecord\Tests;

use Carbon\Carbon;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\Guard;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Connection;
use LdapRecord\Exceptions\AlreadyExistsException;
use LdapRecord\Exceptions\ConstraintViolationException;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Ldap;
use LdapRecord\LdapRecordException;
use LdapRecord\Query\Builder;
use LdapRecord\Testing\LdapFake;

class ConnectionTest extends TestCase
{
    public function test_connection_defaults()
    {
        $conn = new Connection();

        $this->assertInstanceOf(Ldap::class, $conn->getLdapConnection());
        $this->assertInstanceOf(DomainConfiguration::class, $conn->getConfiguration());
        $this->assertEquals($conn->getConfiguration()->all(), (new DomainConfiguration())->all());
    }

    public function test_ldap_connection_can_be_set()
    {
        $conn = new Connection([]);

        $conn->setLdapConnection($ldap = new LdapFake);

        $this->assertEquals($ldap, $conn->getLdapConnection());
    }

    public function test_configuration_can_be_set()
    {
        $conn = new Connection();
        $conn->setConfiguration(['hosts' => ['foo', 'bar']]);
        $this->assertEquals(['foo', 'bar'], $conn->getConfiguration()->get('hosts'));
    }

    public function test_connections_can_create_auth_instance()
    {
        $this->assertInstanceOf(Guard::class, (new Connection())->auth());
    }

    public function test_connections_can_create_queries()
    {
        $this->assertInstanceOf(Builder::class, (new Connection())->query());
    }

    public function test_plain_protocol_and_port_is_used_when_ssl_is_disabled()
    {
        $conn = new Connection(['hosts' => ['127.0.0.1'], 'use_ssl' => false]);
        $this->assertEquals('ldap://127.0.0.1:389', $conn->getLdapConnection()->getHost());
    }

    public function test_ssl_protocol_and_port_is_used_when_enabled()
    {
        $conn = new Connection(['hosts' => ['127.0.0.1'], 'use_ssl' => true]);
        $this->assertEquals('ldaps://127.0.0.1:636', $conn->getLdapConnection()->getHost());
    }

    public function test_reinitialize_using_ssl_swaps_protocol_and_port()
    {
        $conn = new Connection(['hosts' => ['127.0.0.1']]);
        $this->assertEquals('ldap://127.0.0.1:389', $conn->getLdapConnection()->getHost());

        $conn->getConfiguration()->set('use_ssl', true);
        $this->assertEquals('ldap://127.0.0.1:389', $conn->getLdapConnection()->getHost());

        $conn->initialize();
        $this->assertEquals('ldaps://127.0.0.1:636', $conn->getLdapConnection()->getHost());
    }

    public function test_configured_non_standard_port_is_used_when_ssl_is_enabled()
    {
        $conn = new Connection(['hosts' => ['127.0.0.1'], 'port' => 123, 'use_ssl' => true]);
        $this->assertEquals('ldaps://127.0.0.1:123', $conn->getLdapConnection()->getHost());
    }

    public function test_setting_ldap_connection_initializes_it()
    {
        $conn = new Connection(['hosts' => ['127.0.0.1'], 'port' => 123, 'use_ssl' => true]);
        $ldap = new Ldap();
        $this->assertEquals('', $ldap->getHost());

        $conn->setLdapConnection($ldap);
        $this->assertEquals('ldaps://127.0.0.1:123', $ldap->getHost());
    }

    public function test_is_connected()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('isBound')->once()->andReturn(true));

        $this->assertTrue((new Connection([], $ldap))->isConnected());

        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('isBound')->once()->andReturn(false));

        $this->assertFalse((new Connection([], $ldap))->isConnected());
    }

    public function test_reconnect_initializes_connection()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('close')->once())
            ->expect(LdapFake::operation('ssl')->twice()->andReturn(true))
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $conn = new Connection([
            'hosts' => ['127.0.0.1'],
            'use_ssl' => true,
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $conn->reconnect();

        $this->assertEquals('ldaps://127.0.0.1:636', $conn->getLdapConnection()->getHost());
    }

    public function test_auth_username_failure()
    {
        $conn = new Connection();
        $this->expectException(UsernameRequiredException::class);
        $conn->auth()->attempt('', 'password');
    }

    public function test_auth_password_failure()
    {
        $conn = new Connection();
        $this->expectException(PasswordRequiredException::class);
        $conn->auth()->attempt('username', '');
    }

    public function test_auth_failure()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('username', 'password')->andReturn(false))
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $this->assertFalse($conn->auth()->attempt('username', 'password'));
        $this->assertTrue($conn->isConnected());
    }

    public function test_auth_passes_with_rebind()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('username', 'password')->andReturn(true))
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
        $this->assertTrue($conn->isConnected());
    }

    public function test_auth_rebind_failure()
    {
        $this->expectException(BindException::class);

        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('username', 'password')->andReturn(true))
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(false));

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $conn->auth()->attempt('username', 'password');
    }

    public function test_auth_passes_without_rebind()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('username', 'password')->andReturn(true));

        $conn = new Connection([
            'username' => 'test',
            'password' => 'test',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password', true));
        $this->assertTrue($conn->isConnected());
    }

    public function test_connections_are_setup()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('setOption')->once()->with(LDAP_OPT_PROTOCOL_VERSION, 3))
            ->expect(LdapFake::operation('setOption')->once()->with(LDAP_OPT_NETWORK_TIMEOUT, 5))
            ->expect(LdapFake::operation('setOption')->once()->with(LDAP_OPT_REFERRALS, false));

        new Connection(['hosts' => ['foo', 'bar']], $ldap);
    }

    public function test_reconnect()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('close')->once())
            ->expect(LdapFake::operation('connect')->twice())
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $conn->reconnect();
    }

    public function test_ldap_operations_can_be_executed_with_connections()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->andReturn(true));

        $conn = new Connection([], $ldap);

        $executed = false;

        $returned = $conn->run(function ($ldap) use (&$executed) {
            $this->assertInstanceOf(LdapFake::class, $ldap);

            return $executed = true;
        });

        $this->assertTrue($executed);
        $this->assertTrue($returned);
    }

    public function test_ran_ldap_operations_are_retried_when_connection_is_lost()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('close')->times(3))
            ->expect(LdapFake::operation('connect')->times(4))
            ->expect(LdapFake::operation('bind')->andReturn(true));

        $conn = new Connection([
            'hosts' => ['foo', 'bar', 'baz'],
        ], $ldap);

        $called = 0;

        $executed = $conn->run(function () use (&$called) {
            $called++;

            if ($called <= 3) {
                throw new LdapRecordException("Can't contact LDAP server");
            }

            return $called === 4;
        });

        $attempted = $conn->attempted();

        $this->assertTrue($executed);
        $this->assertTrue($conn->isConnected());
        $this->assertCount(2, $attempted);
        $this->assertArrayNotHasKey('baz', $attempted);
        $this->assertInstanceOf(Carbon::class, $attempted['foo']);
        $this->assertInstanceOf(Carbon::class, $attempted['bar']);
    }

    public function test_ran_ldap_operations_are_not_retried_when_other_exception_is_thrown()
    {
        $conn = new Connection();

        $this->expectException(\Exception::class);

        $conn->run(function () {
            throw new \Exception();
        });
    }

    public function test_exception_is_transformed_when_already_exists_error_is_returned()
    {
        $ldap = (new LdapFake)->expect(['bind' => true]);

        $conn = new Connection([], $ldap);

        $this->expectException(AlreadyExistsException::class);

        $conn->run(function () {
            throw new LdapRecordException('Already exists');
        });
    }

    public function test_exception_is_transformed_when_insufficient_access_error_is_returned()
    {
        $ldap = (new LdapFake)->expect(['bind' => true]);

        $conn = new Connection([], $ldap);

        $this->expectException(InsufficientAccessException::class);

        $conn->run(function () {
            throw new LdapRecordException('Insufficient access');
        });
    }

    public function test_exception_is_transformed_when_constraint_violation_error_is_returned()
    {
        $ldap = (new LdapFake)->expect(['bind' => true]);

        $conn = new Connection([], $ldap);

        $this->expectException(ConstraintViolationException::class);

        $conn->run(function () {
            throw new LdapRecordException('Constraint violation');
        });
    }
}
