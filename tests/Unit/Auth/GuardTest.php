<?php

namespace LdapRecord\Tests\Unit\Auth;

use Exception;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\Events\Attempting;
use LdapRecord\Auth\Events\Binding;
use LdapRecord\Auth\Events\Bound;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Auth\Events\Passed;
use LdapRecord\Auth\Guard;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Ldap;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class GuardTest extends TestCase
{
    public function test_attempt_throws_exception_with_an_empty_username()
    {
        $guard = new Guard(new Ldap, new DomainConfiguration);

        $this->expectException(UsernameRequiredException::class);

        $guard->attempt('', 'password');
    }

    public function test_attempt_throws_exception_with_an_empty_password()
    {
        $guard = new Guard(new Ldap, new DomainConfiguration);

        $this->expectException(PasswordRequiredException::class);

        $guard->attempt('username', '');
    }

    public function test_attempt_binds_the_given_credentials_and_rebinds_with_configured_user()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('user', 'pass')->andReturnResponse())
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturnResponse());

        $guard = new Guard($ldap, new DomainConfiguration([
            'username' => 'foo',
            'password' => 'bar',
        ]));

        $this->assertTrue($guard->attempt('user', 'pass'));
    }

    public function test_bind_does_not_rebind_with_configured_user()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('user', 'pass')->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->bind('user', 'pass');
    }

    public function test_bind_allows_null_username_and_password()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with(null, null)->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->bind();
    }

    public function test_bind_always_throws_exception_on_invalid_credentials()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('user', 'pass')->andReturnErrorResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $this->expectException(BindException::class);

        $guard->bind('user', 'pass');
    }

    public function test_bind_as_administrator()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration([
            'username' => 'foo',
            'password' => 'bar',
        ]));

        $guard->bindAsConfiguredUser();
    }

    public function test_binding_events_are_fired_during_bind()
    {
        $events = new Dispatcher;

        $events->listen(Bound::class, function (Bound $event) use (&$firedBound) {
            $this->assertEquals('johndoe', $event->getUsername());
            $this->assertEquals('secret', $event->getPassword());

            $firedBound = true;
        });

        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $guard->bind('johndoe', 'secret');
    }

    public function test_bind_failed_event_includes_exception_thrown()
    {
        $events = m::mock(Dispatcher::class);
        $events->shouldReceive('fire')->once()->with(m::on(fn ($event) => $event instanceof Binding));
        $events->shouldReceive('fire')->once()->with(m::on(
            fn ($event) => $event instanceof Failed && $event->getException() instanceof BindException)
        );

        $this->expectException(BindException::class);

        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andThrow(new Exception('foo'))
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $guard->bind('johndoe', 'secret');
    }

    public function test_auth_events_are_fired_during_attempt()
    {
        $events = new Dispatcher;

        $firedBinding = false;
        $firedBound = false;
        $firedAttempting = false;
        $firedPassed = false;

        $events->listen(Binding::class, function (Binding $event) use (&$firedBinding) {
            $this->assertEquals('johndoe', $event->getUsername());
            $this->assertEquals('secret', $event->getPassword());

            $firedBinding = true;
        });

        $events->listen(Bound::class, function (Bound $event) use (&$firedBound) {
            $this->assertEquals('johndoe', $event->getUsername());
            $this->assertEquals('secret', $event->getPassword());

            $firedBound = true;
        });

        $events->listen(Attempting::class, function (Attempting $event) use (&$firedAttempting) {
            $this->assertEquals('johndoe', $event->getUsername());
            $this->assertEquals('secret', $event->getPassword());

            $firedAttempting = true;
        });

        $events->listen(Passed::class, function (Passed $event) use (&$firedPassed) {
            $this->assertEquals('johndoe', $event->getUsername());
            $this->assertEquals('secret', $event->getPassword());

            $firedPassed = true;
        });

        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $this->assertTrue($guard->attempt('johndoe', 'secret', $bindAsUser = true));

        $this->assertTrue($firedBinding);
        $this->assertTrue($firedBound);
        $this->assertTrue($firedAttempting);
        $this->assertTrue($firedPassed);
    }

    public function test_all_auth_events_can_be_listened_to_with_wildcard()
    {
        $events = new Dispatcher;

        $totalFired = 0;

        $events->listen('LdapRecord\Auth\Events\*', function () use (&$totalFired) {
            $totalFired++;
        });

        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturnResponse()
        );

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $this->assertTrue($guard->attempt('johndoe', 'secret', $bindAsUser = true));
        $this->assertEquals(4, $totalFired);
    }

    public function test_sasl_bind()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('saslBind')
                ->once()
                ->with(null, null, ['mech' => 'GSSAPI'])
                ->andReturnTrue()
        );

        $guard = new Guard($ldap, new DomainConfiguration([
            'use_sasl' => true,
            'sasl_options' => [
                'mech' => 'GSSAPI',
            ],
        ]));

        $this->assertFalse($ldap->isBound());

        $guard->bindAsConfiguredUser();

        $this->assertTrue($ldap->isBound());
    }

    public function test_starttls_is_only_upgraded_once_on_subsequent_binds()
    {
        $ldap = (new LdapFake)->expect([
            LdapFake::operation('bind')->once()->with('admin', 'password')->andReturnResponse(),
            LdapFake::operation('startTLS')->once()->andReturnTrue(),
            LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturnResponse(1),
        ]);

        $ldap->setStartTLS();

        $this->assertFalse($ldap->isSecure());

        $guard = new Guard($ldap, new DomainConfiguration([
            'username' => 'admin',
            'password' => 'password',
        ]));

        $this->assertFalse($guard->attempt('foo', 'bar'));
        $this->assertTrue($ldap->isSecure());
    }
}
