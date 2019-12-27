<?php

namespace LdapRecord\Tests;

use Mockery as m;
use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\DetailedError;
use LdapRecord\LdapInterface;
use LdapRecord\Query\Builder;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;

class ConnectionTest extends TestCase
{
    public function test_connection_creates_ldap_connection()
    {
        $conn = new Connection([]);

        $this->assertInstanceOf(LdapInterface::class, $conn->getLdapConnection());
        $this->assertInstanceOf(DomainConfiguration::class, $conn->getConfiguration());
    }

    public function test_connections_can_create_auth_instance()
    {
        $this->assertInstanceOf(Guard::class, (new Connection())->auth());
    }

    public function test_connections_can_create_queries()
    {
        $this->assertInstanceOf(Builder::class, (new Connection())->query());
    }

    public function test_auth_username_failure()
    {
        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('connect')->once();

        $conn = new Connection();
        $conn->setLdapConnection($ldap);

        $this->expectException(UsernameRequiredException::class);
        $conn->auth()->attempt('', 'password');
    }

    public function test_auth_password_failure()
    {
        $this->expectException(PasswordRequiredException::class);

        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('connect')->once();

        $conn = new Connection();
        $conn->setLdapConnection($ldap);

        $conn->auth()->attempt('username', '');
    }

    public function test_auth_failure()
    {
        $ldap = m::mock(LdapInterface::class);

        // Binding as the user.
        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(false);

        $error = new DetailedError(42, 'Invalid credentials', '80090308: LdapErr: DSID-0C09042A');

        // Binding fails, retrieves last error.
        $ldap->shouldReceive('getLastError')->once()->andReturn('error');
        $ldap->shouldReceive('getDetailedError')->once()->andReturn($error);
        $ldap->shouldReceive('errNo')->once()->andReturn(1);

        // Rebinds as the administrator.
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs([null, null])->andReturn(true);

        $conn = new Connection();
        $conn->setLdapConnection($ldap);

        $this->assertFalse($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_with_rebind()
    {
        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();

        // Authenticates as the user
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true);

        // Re-binds as the administrator
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['foo', 'bar'])->andReturn(true);

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_rebind_failure()
    {
        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();

        // Re-binds as the administrator (fails)
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->withArgs(['test', 'test'])->andReturn(false);
        $ldap->shouldReceive('getLastError')->once()->andReturn('');
        $ldap->shouldReceive('getDetailedError')->once()->andReturn(new DetailedError(null, null, null));
        $ldap->shouldReceive('errNo')->once()->andReturn(1);

        $this->expectException(BindException::class);

        $conn = new Connection([
            'username' => 'test',
            'password' => 'test',
        ], $ldap);

        $conn->connect();

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_without_rebind()
    {
        $ldap = m::mock(LdapInterface::class);
        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true);

        $conn = new Connection([
            'username' => 'test',
            'password' => 'test',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password', true));
    }

    public function test_connections_are_setup()
    {
        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('setOptions')->once()->withArgs([[
            LDAP_OPT_PROTOCOL_VERSION => 3,
            LDAP_OPT_NETWORK_TIMEOUT  => 5,
            LDAP_OPT_REFERRALS        => false,
        ]]);

        $ldap->shouldReceive('connect')->once()->withArgs([['foo', 'bar'], '389']);

        new Connection(['hosts' => ['foo', 'bar']], $ldap);
    }

    public function test_reconnect()
    {
        $ldap = m::mock(LdapInterface::class);
        // Initial connection.
        $ldap->shouldReceive('connect')->twice()->andReturn(true);
        $ldap->shouldReceive('setOptions')->twice();

        // Reconnection.
        $ldap->shouldReceive('close')->once();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['foo', 'bar'])->andReturn(true);

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $conn->reconnect();
    }

    public function test_ldap_operations_can_be_ran_with_connections()
    {
        $conn = new Connection();

        $executed = false;

        $returned = $conn->run(function (LdapInterface $ldap) use (&$executed) {
            $this->assertInstanceOf(LdapInterface::class, $ldap);

            return $executed = true;
        });

        $this->assertTrue($executed);
        $this->assertTrue($returned);
    }

    public function test_ran_ldap_operations_are_retried_when_connection_is_lost()
    {
        $conn = new ReconnectConnectionMock();

        $called = 0;

        $executed = $conn->run(function () use (&$called) {
            $called++;

            if ($called === 1) {
                throw new \Exception('Cannot contact LDAP server');
            }

            return $called === 2;
        });

        $this->assertTrue($executed);
        $this->assertTrue($conn->reconnected);
    }

    public function test_ran_ldap_operations_are_not_retried_when_other_exception_is_thrown()
    {
        $conn = new Connection();

        $this->expectException(\Exception::class);

        $conn->run(function () {
            throw new \Exception();
        });
    }
}

class ReconnectConnectionMock extends Connection
{
    public $reconnected = false;

    public function reconnect()
    {
        $this->reconnected = true;
    }
}
