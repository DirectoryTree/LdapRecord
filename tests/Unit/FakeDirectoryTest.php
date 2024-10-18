<?php

namespace LdapRecord\Tests\Unit;

use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\DetailedError;
use LdapRecord\LdapResultResponse;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class FakeDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);

        DirectoryFake::setup();
    }

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();

        parent::tearDown();
    }

    public function test_connection_is_replaced_with_fake()
    {
        $this->assertInstanceOf(ConnectionFake::class, Container::getConnection());
        $this->assertInstanceOf(ConnectionFake::class, Container::getDefaultConnection());
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
            'protocol' => null,
            'use_tls' => true,
            'use_ssl' => false,
            'use_sasl' => false,
            'allow_insecure_password_changes' => false,
            'timeout' => 5,
            'version' => 3,
            'follow_referrals' => false,
            'options' => ['foo'],
            'sasl_options' => [],
        ];

        Container::addConnection(new Connection($config), 'local');

        $fake = DirectoryFake::setup('local');

        $this->assertEquals($fake->getConfiguration()->all(), $config);
    }

    public function test_auth_fails()
    {
        $conn = Container::getConnection('default');

        $conn->getLdapConnection()->expect(
            LdapFake::operation('bind')->once()->andReturnErrorResponse()
        );

        $this->assertFalse($conn->auth()->attempt('user', 'secret'));
    }

    public function test_auth_fails_without_proper_username()
    {
        $conn = Container::getConnection('default');

        $conn->getLdapConnection()->expect(['add' => true]);

        $conn->actingAs(User::create(['cn' => 'John']));

        $this->assertFalse($conn->auth()->attempt('user', 'secret'));
    }

    public function test_auth_passes()
    {
        $conn = Container::getConnection('default');

        $conn->getLdapConnection()->expect([
            LdapFake::operation('add')->once()->andReturnTrue(),
            LdapFake::operation('bind')->once()->andReturnErrorResponse(),
            LdapFake::operation('bind')->once()->andReturnResponse(),
        ]);

        $user = User::create(['cn' => 'John']);

        $this->assertFalse($conn->auth()->attempt($user->getDn(), 'secret'));

        $conn->actingAs($user);

        $this->assertTrue($conn->auth()->attempt($user->getDn(), 'secret'));
    }

    public function test_bind_failure_with_error_code()
    {
        $conn = Container::getConnection('default');

        $conn->getLdapConnection()
            ->expect(LdapFake::operation('bind')->andReturnErrorResponse())
            ->shouldReturnErrorNumber(200)
            ->shouldReturnError('Last Error')
            ->shouldReturnDiagnosticMessage('Diagnostic Message');

        try {
            $conn->auth()->bind('user', 'secret');

            $this->fail('Bind exception was not thrown.');
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

        $alpha = DirectoryFake::setup('alpha');
        $alpha->getLdapConnection()->expect(['bind' => new LdapResultResponse]);

        $bravo = DirectoryFake::setup('bravo');
        $bravo->getLdapConnection()->expect(['bind' => new LdapResultResponse(1)]);

        $this->assertEquals(['alpha'], $alpha->getConfiguration()->get('hosts'));
        $this->assertEquals(['bravo'], $bravo->getConfiguration()->get('hosts'));

        $this->assertTrue($alpha->auth()->attempt('johndoe', 'secret'));
        $this->assertFalse($bravo->auth()->attempt('johndoe', 'secret'));
    }
}
