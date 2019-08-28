<?php

namespace LdapRecord\Tests\Log;

use Mockery as m;
use Psr\Log\LoggerInterface;
use LdapRecord\Tests\TestCase;
use LdapRecord\Log\EventLogger;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Auth\Events\Event as AuthEvent;
use LdapRecord\Models\Events\Event as ModelEvent;
use LdapRecord\Connections\ConnectionInterface;

class EventLoggerTest extends TestCase
{
    public function test_auth_events_are_logged()
    {
        $e = m::mock(AuthEvent::class);
        $l = m::mock(LoggerInterface::class);
        $c = m::mock(ConnectionInterface::class);

        $log = 'LDAP (ldap://192.168.1.1) - Connection: domain-a - Operation: Mockery_4_LdapRecord_Auth_Events_Event - Username: jdoe@acme.org';

        $l->shouldReceive('info')->once()->with($log);

        $c
            ->shouldReceive('getHost')->once()->andReturn('ldap://192.168.1.1')
            ->shouldReceive('getName')->once()->andReturn('domain-a');

        $e
            ->shouldReceive('getConnection')->once()->andReturn($c)
            ->shouldReceive('getUsername')->once()->andReturn('jdoe@acme.org');

        $eLogger = new EventLogger($l);

        $this->assertNull($eLogger->auth($e));
    }

    public function test_failed_auth_event_reports_result()
    {
        $l = m::mock(LoggerInterface::class);
        $c = m::mock(ConnectionInterface::class);

        $e = new Failed($c, 'jdoe@acme.org', 'super-secret');

        $log = 'LDAP (ldap://192.168.1.1) - Connection: domain-a - Operation: LdapRecord\Auth\Events\Failed - Username: jdoe@acme.org - Reason: Invalid Credentials';

        $l->shouldReceive('warning')->once()->with($log);

        $c
            ->shouldReceive('getHost')->once()->andReturn('ldap://192.168.1.1')
            ->shouldReceive('getName')->once()->andReturn('domain-a')
            ->shouldReceive('getLastError')->once()->andReturn('Invalid Credentials');

        $eLogger = new EventLogger($l);

        $this->assertNull($eLogger->auth($e));
    }

    public function test_model_events_are_logged()
    {
        $c = m::mock(ConnectionInterface::class);

        $b = $this->newBuilder($c);

        $dn = 'cn=John Doe,dc=corp,dc=acme,dc=org';

        $u = new User(['dn' => $dn], $b);

        $l = m::mock(LoggerInterface::class);

        $eLogger = new EventLogger($l);

        $me = m::mock(ModelEvent::class);

        $me->shouldReceive('getModel')->once()->andReturn($u);

        $log = "LDAP (ldap://192.168.1.1) - Connection: domain-a - Operation: Mockery_6_LdapRecord_Models_Events_Event - On: LdapRecord\Models\User - Distinguished Name: $dn";

        $l->shouldReceive('info')->once()->with($log);

        $c
            ->shouldReceive('getHost')->once()->andReturn('ldap://192.168.1.1')
            ->shouldReceive('getName')->once()->andReturn('domain-a');

        $this->assertNull($eLogger->model($me));
    }
}
