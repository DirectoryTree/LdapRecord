<?php

namespace LdapRecord\Tests;

use Mockery as m;
use LdapRecord\Connection;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Events\Connected;
use LdapRecord\Events\Connecting;
use LdapRecord\LdapRecordException;
use LdapRecord\Events\ConnectionFailed;
use LdapRecord\Events\DispatcherInterface;

class ConnectionEventsTest extends TestCase
{
    public function test_successful_connection_dispatches_proper_events()
    {
        $ldap = (new LdapFake)->shouldBindOnceWith('user');

        $conn = new Connection([
            'hosts' => ['one'],
            'username' => 'user',
        ], $ldap);

        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('dispatch')->with(Connecting::class)->once();
        $dispatcher->shouldReceive('dispatch')->with(Connected::class)->once();
        $dispatcher->shouldNotReceive('dispatch')->with(ConnectionFailed::class);

        $conn->setDispatcher($dispatcher);

        $this->assertInstanceOf(Connection::class, $conn->connect());
    }

    public function test_failed_connection_dispatches_proper_events()
    {
        $conn = new Connection([], new LdapFake);

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
            ->shouldFailBindOnceWith('user')
            ->shouldFailBindOnceWith('user')
            ->shouldBindOnceWith('user')
            ->shouldReturnError("Can't contact LDAP server");

        $conn = new Connection([
            'hosts' => ['one', 'two', 'three'],
            'username' => 'user',
        ], $ldap);

        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('dispatch')->with(Connecting::class)->times(3);
        $dispatcher->shouldReceive('dispatch')->withArgs(function (Connected $event) {
            return array_keys($event->connection->attempted()) === ['one', 'two'];
        })->once();
        $dispatcher->shouldNotReceive('dispatch')->with(ConnectionFailed::class);

        $conn->setDispatcher($dispatcher);

        $conn->connect();
    }
}
