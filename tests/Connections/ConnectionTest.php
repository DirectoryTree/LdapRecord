<?php

namespace LdapRecord\Tests;

use Mockery as m;
use LdapRecord\Connection;
use LdapRecord\DetailedError;
use LdapRecord\LdapInterface;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;

class ConnectionTest extends TestCase
{
    public function test_connection_creates_ldap_connection()
    {
        $conn = new Connection(new DomainConfiguration());

        $this->assertInstanceOf(LdapInterface::class, $conn->getLdapConnection());
        $this->assertInstanceOf(DomainConfiguration::class, $conn->getConfiguration());
    }

    public function test_auth_username_failure()
    {
        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('connect')->once();
        $ldap->shouldReceive('isBound')->once()->andReturn(true);
        $ldap->shouldReceive('close')->once()->andReturn(true);

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
        $ldap->shouldReceive('isBound')->once()->andReturn(true);
        $ldap->shouldReceive('close')->once()->andReturn(true);

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
        $ldap->shouldReceive('isBound')->once()->andReturn(true);
        $ldap->shouldReceive('errNo')->once()->andReturn(1);

        // Rebinds as the administrator.
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs([null, null])->andReturn(true);

        // Closes the connection.
        $ldap->shouldReceive('close')->once()->andReturn(true);

        $conn = new Connection();
        $conn->setLdapConnection($ldap);

        $this->assertFalse($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_with_rebind()
    {
        $config = new DomainConfiguration([
            'username' => 'foo',
            'password' => 'bar',
        ]);

        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('isBound')->once()->andReturn(true);

        // Authenticates as the user
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true);

        // Re-binds as the administrator
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['foo', 'bar'])->andReturn(true);
        $ldap->shouldReceive('close')->once()->andReturn(true);

        $conn = new Connection($config);
        $conn->setLdapConnection($ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_rebind_failure()
    {
        $config = new DomainConfiguration([
            'username' => 'test',
            'password' => 'test',
        ]);

        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();

        // Re-binds as the administrator (fails)
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->withArgs(['test', 'test'])->andReturn(false);
        $ldap->shouldReceive('getLastError')->once()->andReturn('');
        $ldap->shouldReceive('getDetailedError')->once()->andReturn(new DetailedError(null, null, null));
        $ldap->shouldReceive('isBound')->once()->andReturn(true);
        $ldap->shouldReceive('errNo')->once()->andReturn(1);
        $ldap->shouldReceive('close')->once()->andReturn(true);

        $this->expectException(BindException::class);

        $conn = new Connection($config);
        $conn->setLdapConnection($ldap);
        $conn->connect();

        $this->assertTrue($conn->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_without_rebind()
    {
        $config = new DomainConfiguration([
            'username' => 'test',
            'password' => 'test',
        ]);

        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('connect')->once()->andReturn(true);
        $ldap->shouldReceive('setOptions')->once();
        $ldap->shouldReceive('isUsingTLS')->once()->andReturn(false);
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true);
        $ldap->shouldReceive('isBound')->once()->andReturn(true);
        $ldap->shouldReceive('close')->once()->andReturn(true);

        $conn = new Connection($config);
        $conn->setLdapConnection($ldap);

        $this->assertTrue($conn->auth()->attempt('username', 'password', true));
    }

    public function test_prepare_connection()
    {
        $config = m::mock(DomainConfiguration::class);

        $config->shouldReceive('get')->withArgs(['hosts'])->once()->andReturn('host');
        $config->shouldReceive('get')->withArgs(['port'])->once()->andReturn('389');
        $config->shouldReceive('get')->withArgs(['use_ssl'])->once()->andReturn(false);
        $config->shouldReceive('get')->withArgs(['use_tls'])->once()->andReturn(false);
        $config->shouldReceive('get')->withArgs(['version'])->once()->andReturn(3);
        $config->shouldReceive('get')->withArgs(['timeout'])->once()->andReturn(5);
        $config->shouldReceive('get')->withArgs(['follow_referrals'])->andReturn(false);

        // Setting LDAP_OPT_PROTOCOL_VERSION to "2" here enforces the documented behavior of honoring the
        // "version" key over LDAP_OPT_PROTOCOL_VERSION in custom_options.
        $config->shouldReceive('get')->withArgs(['options'])->andReturn([LDAP_OPT_PROTOCOL_VERSION => 2]);

        $ldap = m::mock(LdapInterface::class);

        $ldap->shouldReceive('setOptions')->once()->withArgs([[
            LDAP_OPT_PROTOCOL_VERSION => 3,
            LDAP_OPT_NETWORK_TIMEOUT  => 5,
            LDAP_OPT_REFERRALS        => false,
        ]]);
        $ldap->shouldReceive('connect')->once()->withArgs(['host', '389']);
        $ldap->shouldReceive('isBound')->once()->andReturn(false);

        $conn = new Connection($config, $ldap);

        $this->assertInstanceOf(DomainConfiguration::class, $conn->getConfiguration());
    }
}
