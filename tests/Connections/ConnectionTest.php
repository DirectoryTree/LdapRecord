<?php

namespace LdapRecord\Tests\Connections;

use LdapRecord\Tests\TestCase;
use LdapRecord\Connections\Connection;
use LdapRecord\Connections\LdapInterface;
use LdapRecord\Connections\DetailedError;
use LdapRecord\Models\Factory as ModelFactory;
use LdapRecord\Configuration\DomainConfiguration;

class ConnectionTest extends TestCase
{
    protected function newConnection($configuration = [])
    {
        return new Connection($configuration);
    }

    public function test_construct()
    {
        $m = $this->newConnection(new DomainConfiguration());

        $this->assertInstanceOf(LdapInterface::class, $m->getLdapConnection());
        $this->assertInstanceOf(DomainConfiguration::class, $m->getConfiguration());
    }

    public function test_auth_username_failure()
    {
        $this->expectException(\LdapRecord\Auth\UsernameRequiredException::class);

        $ldap = $this->newLdapMock();

        $ldap
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('connect')->once()
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection();

        $c->setLdapConnection($ldap);

        $c->auth()->attempt(0000000, 'password');
    }

    public function test_auth_password_failure()
    {
        $this->expectException(\LdapRecord\Auth\PasswordRequiredException::class);

        $ldap = $this->newLdapMock();

        $ldap
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('connect')->once()
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection();

        $c->setLdapConnection($ldap);

        $c->auth()->attempt('username', 0000000);
    }

    public function test_auth_failure()
    {
        $ldap = $this->newLdapMock();

        // Binding as the user.
        $ldap
            ->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(false);

        $error = new DetailedError(42, 'Invalid credentials', '80090308: LdapErr: DSID-0C09042A');

        // Binding fails, retrieves last error.
        $ldap->shouldReceive('getLastError')->once()->andReturn('error')
            ->shouldReceive('getDetailedError')->once()->andReturn($error)
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('errNo')->once()->andReturn(1);

        // Rebinds as the administrator.
        $ldap->shouldReceive('bind')->once()->withArgs([null, null])->andReturn(true);

        // Closes the connection.
        $ldap->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection();

        $c->setLdapConnection($ldap);

        $this->assertFalse($c->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_with_rebind()
    {
        $config = new DomainConfiguration([
            'username' => 'test',
            'password' => 'test',
        ]);

        $ldap = $this->newLdapMock();

        $ldap
            ->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('isUsingSSL')->once()->andReturn(false)
            ->shouldReceive('isBound')->once()->andReturn(true);

        // Authenticates as the user
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true);

        // Re-binds as the administrator
        $ldap
            ->shouldReceive('bind')->once()->withArgs(['test', 'test'])->andReturn(true)
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection($config);

        $c->setLdapConnection($ldap);

        $this->assertTrue($c->auth()->attempt('username', 'password'));
    }

    public function test_auth_rebind_failure()
    {
        $this->expectException(\LdapRecord\Auth\BindException::class);

        $config = new DomainConfiguration([
            'username' => 'test',
            'password' => 'test',
        ]);

        $ldap = $this->newLdapMock();

        $ldap
            ->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('isUsingSSL')->once()->andReturn(false)
            ->shouldReceive('isBound')->once()->andReturn(true);

        // Authenticates as the user
        $ldap->shouldReceive('bind')->once()->withArgs(['username', 'password']);

        // Re-binds as the administrator (fails)
        $ldap->shouldReceive('bind')->once()->withArgs(['test', 'test'])->andReturn(false)
            ->shouldReceive('getLastError')->once()->andReturn('')
            ->shouldReceive('getDetailedError')->once()->andReturn(new DetailedError(null, null, null))
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('errNo')->once()->andReturn(1)
            ->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection($config);

        $c->setLdapConnection($ldap);

        $c->connect();

        $this->assertTrue($c->auth()->attempt('username', 'password'));
    }

    public function test_auth_passes_without_rebind()
    {
        $config = new DomainConfiguration([
            'username' => 'test',
            'password' => 'test',
        ]);

        $ldap = $this->newLdapMock();

        $ldap->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('isUsingSSL')->once()->andReturn(false)
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('bind')->once()->withArgs(['username', 'password'])->andReturn(true)
            ->shouldReceive('getLastError')->once()->andReturn('')
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('close')->once()->andReturn(true);

        $c = $this->newConnection($config);

        $c->setLdapConnection($ldap);

        $this->assertTrue($c->auth()->attempt('username', 'password', true));
    }

    public function test_prepare_connection()
    {
        $config = $this->mock(DomainConfiguration::class);

        $config
            ->shouldReceive('get')->withArgs(['hosts'])->once()->andReturn('')
            ->shouldReceive('get')->withArgs(['port'])->once()->andReturn('389')
            ->shouldReceive('get')->withArgs(['schema'])->once()->andReturn('LdapRecord\Schemas\ActiveDirectory')
            ->shouldReceive('get')->withArgs(['use_ssl'])->once()->andReturn(false)
            ->shouldReceive('get')->withArgs(['use_tls'])->once()->andReturn(false)
            ->shouldReceive('get')->withArgs(['version'])->once()->andReturn(3)
            ->shouldReceive('get')->withArgs(['timeout'])->once()->andReturn(5)
            ->shouldReceive('get')->withArgs(['follow_referrals'])->andReturn(false)
            // Setting LDAP_OPT_PROTOCOL_VERSION to "2" here enforces the documented behavior of honoring the
            // "version" key over LDAP_OPT_PROTOCOL_VERSION in custom_options.
            ->shouldReceive('get')->withArgs(['custom_options'])->andReturn([LDAP_OPT_PROTOCOL_VERSION => 2]);

        $ldap = $this->mock(LdapInterface::class);

        $ldap
            ->shouldReceive('setOptions')->once()->withArgs([[
                LDAP_OPT_PROTOCOL_VERSION => 3,
                LDAP_OPT_NETWORK_TIMEOUT => 5,
                LDAP_OPT_REFERRALS => false,
            ]])
            ->shouldReceive('connect')->once()
            ->shouldReceive('isBound')->once()->andReturn(false);

        $c = new Connection($config);

        $c->setLdapConnection($ldap);

        $this->assertInstanceOf(DomainConfiguration::class, $c->getConfiguration());
    }

    public function test_make()
    {
        $m = $this->newConnection();

        $this->assertInstanceOf(ModelFactory::class, $m->make());
    }
}
