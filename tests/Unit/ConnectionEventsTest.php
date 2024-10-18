<?php

namespace LdapRecord\Tests\Unit;

use LdapRecord\Connection;
use LdapRecord\Events\Connected;
use LdapRecord\Events\Connecting;
use LdapRecord\Events\ConnectionFailed;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ConnectionEventsTest extends TestCase
{
    public function test_successful_connection_dispatches_proper_events()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->with('user', $this->anything())->andReturnResponse());

        $conn = new Connection([
            'hosts' => ['one'],
            'username' => 'user',
        ], $ldap);

        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('dispatch')->with(Connecting::class)->once();
        $dispatcher->shouldReceive('dispatch')->with(Connected::class)->once();
        $dispatcher->shouldNotReceive('dispatch')->with(ConnectionFailed::class);

        $conn->setDispatcher($dispatcher);

        $conn->connect();
    }

    public function test_failed_connection_dispatches_proper_events()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->andReturnErrorResponse()
        );

        $conn = new Connection([], $ldap);

        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('dispatch')->with(Connecting::class)->once();
        $dispatcher->shouldReceive('dispatch')->with(ConnectionFailed::class)->once();
        $dispatcher->shouldNotReceive('dispatch')->with(Connected::class);

        $conn->setDispatcher($dispatcher);

        $this->expectException(LdapRecordException::class);

        $conn->connect();
    }

    public function test_connection_retries_subsequent_hosts_until_successful()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->with('user')->twice()->andReturnErrorResponse())
            ->expect(LdapFake::operation('bind')->with('user')->once()->andReturnResponse())
            ->shouldReturnError("Can't contact LDAP server");

        $conn = new Connection([
            'hosts' => ['one', 'two', 'three'],
            'username' => 'user',
        ], $ldap);

        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('dispatch')->with(Connecting::class)->times(3);
        $dispatcher->shouldReceive('dispatch')->withArgs(function (Connected $event) {
            return array_keys($event->getConnection()->attempted()) === ['one', 'two'];
        })->once();
        $dispatcher->shouldNotReceive('dispatch')->with(ConnectionFailed::class);

        $conn->setDispatcher($dispatcher);

        $conn->connect();
    }
}
