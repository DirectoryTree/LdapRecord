<?php

namespace LdapRecord\Tests;

use Mockery as m;
use Carbon\Carbon;
use LdapRecord\Ldap;
use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\DetailedError;
use LdapRecord\Query\Builder;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Exceptions\AlreadyExistsException;
use LdapRecord\Exceptions\InsufficientAccessException;
use LdapRecord\Exceptions\ConstraintViolationException;

class ConnectionTest extends TestCase
{
    use CreatesConnectedLdapMocks;

    public function test_connection_defaults()
    {
        $conn = new Connection();

        $this->assertInstanceOf(Ldap::class, $conn->getLdapConnection());
        $this->assertInstanceOf(DomainConfiguration::class, $conn->getConfiguration());
        $this->assertEquals($conn->getConfiguration()->all(), (new DomainConfiguration())->all());
    }

    public function test_ldap_connection_can_be_set()
    {
        $conn = new Connection();
        $ldap = $this->newConnectedLdapMock();
        $conn->setLdapConnection($ldap);
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
        $ldap = $this->newConnectedLdapMock();
        $conn = new Connection([], $ldap);
        $this->assertTrue($conn->isConnected());
    }

    public function test_reconnect_initializes_connection()
    {
        $ldap = m::mock(Ldap::class);
        $ldap->makePartial();
        $ldap->shouldAllowMockingProtectedMethods();
        $ldap->shouldReceive('close')->once()->withNoArgs();
        $ldap->shouldReceive('setOptions')->twice()->withAnyArgs();
        $ldap->shouldReceive('ssl')->twice()->withNoArgs();
        $ldap->shouldReceive('bind')->once()->with('foo', 'bar')->andReturnTrue();

        $conn = new Connection(['hosts' => ['127.0.0.1'], 'use_ssl' => true, 'username' => 'foo', 'password' => 'bar'], $ldap);
        $conn->reconnect();
        $this->assertEquals('ldap://127.0.0.1:389', $conn->getLdapConnection()->getHost());
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
        $ldap = $this->newConnectedLdapMock();

        // Binding as the user.
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with('username', 'password')->andReturn(false);

        $error = new DetailedError(42, 'Invalid credentials', '80090308: LdapErr: DSID-0C09042A');

        // Binding fails, retrieves last error.
        $ldap->shouldReceive('getLastError')->once()->andReturn('error');
        $ldap->shouldReceive('getDetailedError')->once()->andReturn($error);
        $ldap->shouldReceive('errNo')->once()->andReturn(1);

        // Rebinds as the administrator.
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with(null, null)->andReturn(true);

        $conn = new Connection([], $ldap);

        $this->assertFalse($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_with_rebind()
    {
        $ldap = $this->newConnectedLdapMock();

        // Authenticates as the user
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with('username', 'password')->andReturn(true);

        // Re-binds as the administrator
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with('foo', 'bar')->andReturn(true);

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_rebind_failure()
    {
        $ldap = $this->newConnectedLdapMock();

        // Re-binds as the administrator (fails)
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->with('test', 'test')->andReturn(false);
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
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with('username', 'password')->andReturn(true);

        $conn = new Connection([
            'username' => 'test',
            'password' => 'test',
        ], $ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password', true));
    }

    public function test_connections_are_setup()
    {
        $ldap = m::mock(Ldap::class);

        $ldap->shouldReceive('setOptions')->once()->with([
            LDAP_OPT_PROTOCOL_VERSION => 3,
            LDAP_OPT_NETWORK_TIMEOUT  => 5,
            LDAP_OPT_REFERRALS        => false,
        ]);

        $ldap->shouldReceive('connect')->once()->with('foo', '389');

        new Connection(['hosts' => ['foo', 'bar']], $ldap);
    }

    public function test_reconnect()
    {
        $ldap = m::mock(Ldap::class);
        // Initial connection.
        $ldap->shouldReceive('connect')->twice()->andReturn(true);
        $ldap->shouldReceive('setOptions')->twice();

        // Reconnection.
        $ldap->shouldReceive('close')->once();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->with('foo', 'bar')->andReturn(true);

        $conn = new Connection([
            'username' => 'foo',
            'password' => 'bar',
        ], $ldap);

        $conn->reconnect();
    }

    public function test_ldap_operations_can_be_ran_with_connections()
    {
        $ldap = $this->newConnectedLdapMock();
        $conn = new Connection([], $ldap);

        $executed = false;

        $returned = $conn->run(function (Ldap $ldap) use (&$executed) {
            $this->assertInstanceOf(Ldap::class, $ldap);

            return $executed = true;
        });

        $this->assertTrue($executed);
        $this->assertTrue($returned);
    }

    public function test_ran_ldap_operations_are_retried_when_connection_is_lost()
    {
        $ldap = $this->newConnectedLdapMock();

        $ldap->shouldReceive('getDetailedError')->times(3)->andReturnNull();

        $conn = new ReconnectConnectionMock([
            'hosts' => ['foo', 'bar', 'baz'],
        ], $ldap);

        $called = 0;

        $executed = $conn->run(function () use (&$called) {
            $called++;

            if ($called <= 3) {
                throw new \Exception("Can't contact LDAP server");
            }

            return $called === 4;
        });

        $attempted = $conn->attempted();

        $this->assertTrue($executed);
        $this->assertTrue($conn->reconnected);
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
        $conn = new Connection([], $ldapMock = $this->newConnectedLdapMock());

        $ldapMock->shouldReceive('getDetailedError')->once()->andReturnNull();

        $this->expectException(AlreadyExistsException::class);

        $conn->run(function () {
            throw new \Exception('Already exists');
        });
    }

    public function test_exception_is_transformed_when_insufficient_access_error_is_returned()
    {
        $conn = new Connection([], $ldapMock = $this->newConnectedLdapMock());

        $ldapMock->shouldReceive('getDetailedError')->once()->andReturnNull();

        $this->expectException(InsufficientAccessException::class);

        $conn->run(function () {
            throw new \Exception('Insufficient access');
        });
    }

    public function test_exception_is_transformed_when_constraint_violation_error_is_returned()
    {
        $conn = new Connection([], $ldapMock = $this->newConnectedLdapMock());

        $ldapMock->shouldReceive('getDetailedError')->once()->andReturnNull();

        $this->expectException(ConstraintViolationException::class);

        $conn->run(function () {
            throw new \Exception('Constraint violation');
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
