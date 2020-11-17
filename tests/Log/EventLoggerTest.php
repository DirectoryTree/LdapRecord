<?php

namespace LdapRecord\Tests\Log;

use Mockery as m;
use LdapRecord\Ldap;
use LdapRecord\Connection;
use Psr\Log\LoggerInterface;
use LdapRecord\Tests\TestCase;
use LdapRecord\Events\Logger;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Auth\Events\Event as AuthEvent;

class EventLoggerTest extends TestCase
{
    public function test_auth_events_are_logged()
    {
        $event = m::mock(AuthEvent::class);
        $logger = m::mock(LoggerInterface::class);
        $connection = m::mock(Connection::class);

        $logger->shouldReceive('info')->once()->withArgs(function ($logged) {
            return strpos($logged, 'LDAP (ldap://192.168.1.1)') !== false &&
                strpos($logged, 'Username: jdoe@acme.org') !== false;
        });

        $connection->shouldReceive('getHost')->once()->andReturn('ldap://192.168.1.1');

        $event
            ->shouldReceive('getConnection')->once()->andReturn($connection)
            ->shouldReceive('getUsername')->once()->andReturn('jdoe@acme.org');

        $eLogger = new Logger($logger);

        $eLogger->log($event);
    }

    public function test_failed_auth_event_reports_result()
    {
        $logger = m::mock(LoggerInterface::class);
        $ldap = m::mock(Ldap::class);

        $event = new Failed($ldap, 'jdoe@acme.org', 'super-secret');

        $log = 'LDAP (ldap://192.168.1.1) - Operation: Failed - Username: jdoe@acme.org - Reason: Invalid Credentials';

        $logger->shouldReceive('warning')->once()->with($log);

        $ldap
            ->shouldReceive('getHost')->once()->andReturn('ldap://192.168.1.1')
            ->shouldReceive('getLastError')->once()->andReturn('Invalid Credentials');

        $eLogger = new Logger($logger);

        $eLogger->log($event);
    }
}
