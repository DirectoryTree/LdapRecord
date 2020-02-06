<?php

namespace LdapRecord\Tests;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\DetailedError;
use LdapRecord\Auth\BindException;
use LdapRecord\Testing\FakeConnection;
use LdapRecord\Testing\FakeDirectory;
use LdapRecord\Models\ActiveDirectory\User;

class FakeDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection());

        FakeDirectory::setup();
    }

    public function test_connection_is_replaced_with_fake()
    {
        $this->assertInstanceOf(FakeConnection::class, Container::getConnection(null));
        $this->assertInstanceOf(FakeConnection::class, Container::getDefaultConnection());
    }

    public function test_connection_fake_is_connected()
    {
        $this->assertTrue(Container::getDefaultConnection()->isConnected());
    }

    public function test_fake_connection_uses_real_connections_config()
    {
        $config = [
            'hosts' => ['localhost'],
            'base_dn' => 'dc=local,dc=com',
            'username' => 'user',
            'password' => 'pass',
            'port' => 389,
            'use_tls' => true,
            'use_ssl' => false,
            'timeout' => 5,
            'version' => 3,
            'follow_referrals' => false,
            'options' => ['foo']
        ];

        Container::addConnection(new Connection($config),'local');

        $fake = FakeDirectory::setup('local');

        $this->assertEquals($fake->getConfiguration()->all(), $config);
    }

    public function test_auth_fails()
    {
        $conn = Container::getConnection('default');
        $this->assertFalse($conn->auth()->attempt('user', 'secret'));
    }

    public function test_auth_fails_without_proper_username()
    {
        $conn = Container::getConnection('default');
        $conn->actingAs(User::create(['cn' => 'John']));
        $this->assertFalse($conn->auth()->attempt('user', 'secret'));
    }

    public function test_auth_passes()
    {
        $user = User::create(['cn' => 'John']);

        $conn = Container::getConnection('default');

        $this->assertFalse($conn->auth()->attempt($user->getDn(), 'secret'));

        $conn->actingAs($user);

        $this->assertTrue($conn->auth()->attempt($user->getDn(), 'secret'));
    }

    public function test_bind_failure_with_error_code()
    {
        $conn = Container::getConnection('default');

        $conn->getLdapConnection()
            ->shouldReturnErrorNumber(200)
            ->shouldReturnError('Last Error')
            ->shouldReturnDiagnosticMessage('Diagnostic Message');

        try {
            $conn->auth()->bind('user', 'secret');

            $this->fail('Bind exception not thrown.');
        } catch (BindException $ex) {
            $detailedError = $ex->getDetailedError();

            $this->assertInstanceOf(DetailedError::class, $detailedError);
            $this->assertEquals(200, $detailedError->getErrorCode());
            $this->assertEquals('Last Error', $detailedError->getErrorMessage());
            $this->assertEquals('Diagnostic Message', $detailedError->getDiagnosticMessage());
        }
    }

    public function test_multiple_fake_directories()
    {
        Container::addConnection(new Connection(['hosts' => ['alpha']]), 'alpha');
        Container::addConnection(new Connection(['hosts' => ['bravo']]), 'bravo');

        $alpha = FakeDirectory::setup('alpha');
        $bravo = FakeDirectory::setup('bravo');

        $this->assertEquals(['alpha'], $alpha->getConfiguration()->get('hosts'));
        $this->assertEquals(['bravo'], $bravo->getConfiguration()->get('hosts'));

        $user = User::create(['cn'=> 'John Doe']);
        $alpha->actingAs($user);
        $this->assertTrue($alpha->auth()->attempt($user->getDn(), 'secret'));

        $this->assertFalse($bravo->auth()->attempt($user->getDn(), 'secret'));
    }
}