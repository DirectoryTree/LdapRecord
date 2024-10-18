<?php

namespace LdapRecord\Tests\Unit\Log;

use LdapRecord\Auth\BindException;
use LdapRecord\Auth\Events\Bound;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Events\Logger;
use LdapRecord\Query\Events\QueryExecuted;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use Psr\Log\LoggerInterface;

class EventLoggerTest extends TestCase
{
    public function test_auth_events_are_logged()
    {
        $ldap = new LdapFake;

        $ldap->connect('localhost');

        $event = new Bound($ldap, 'jdoe@email.com', 'secret');

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('info')->once()->with(
            'LDAP (ldap://localhost:389) - Operation: Bound - Username: jdoe@email.com'
        );

        $eLogger = new Logger($logger);

        $eLogger->log($event);
    }

    public function test_failed_auth_event_reports_result()
    {
        $ldap = (new LdapFake)->shouldReturnError('Invalid Credentials');

        $ldap->connect('localhost');

        $event = new Failed($ldap, 'jdoe@acme.org', 'super-secret', new BindException);

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('warning')->once()->with(
            'LDAP (ldap://localhost:389) - Operation: Failed - Username: jdoe@acme.org - Reason: Invalid Credentials'
        );

        $eLogger = new Logger($logger);

        $eLogger->log($event);
    }

    public function test_queries_are_logged()
    {
        $ldap = (new LdapFake)->expect(['search' => []]);

        $conn = new ConnectionFake([
            'base_dn' => 'dc=local,dc=com',
            'hosts' => ['localhost'],
        ], $ldap);

        $conn->shouldBeConnected()->connect();

        $query = $conn->query()->where('foo', '=', 'bar');

        $query->get();

        $event = new QueryExecuted($query, 2.5);

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('info')->once()->with(
            'LDAP (ldap://localhost:389) - Operation: QueryExecuted - Base DN: dc=local,dc=com - Filter: (foo=\62\61\72) - Selected: (*) - Time Elapsed: 2.5'
        );

        $eLogger = new Logger($logger);

        $eLogger->log($event);
    }
}
