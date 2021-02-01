<?php

namespace LdapRecord\Tests\Auth;

use LdapRecord\Ldap;
use LdapRecord\Auth\Guard;
use LdapRecord\Tests\TestCase;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Auth\Events\Bound;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Auth\BindException;
use LdapRecord\Auth\Events\Passed;
use LdapRecord\Auth\Events\Binding;
use LdapRecord\Auth\Events\Attempting;
use LdapRecord\Auth\PasswordRequiredException;
use LdapRecord\Auth\UsernameRequiredException;
use LdapRecord\Configuration\DomainConfiguration;

class GuardTest extends TestCase
{
    public function test_attempt_throws_exception_with_an_empty_username()
    {
        $guard = new Guard(new Ldap(), new DomainConfiguration());

        $this->expectException(UsernameRequiredException::class);

        $guard->attempt('', 'password');
    }

    public function test_attempt_throws_exception_with_an_empty_password()
    {
        $guard = new Guard(new Ldap(), new DomainConfiguration());

        $this->expectException(PasswordRequiredException::class);

        $guard->attempt('username', '');
    }

    public function test_attempt_binds_the_given_credentials_and_rebinds_with_configured_user()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('user', 'pass')->andReturn(true))
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration([
            'username' => 'foo',
            'password' => 'bar',
        ]));

        $this->assertTrue($guard->attempt('user', 'pass'));
    }

    public function test_bind_does_not_rebind_with_configured_user()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('user', 'pass')->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration);

        $this->assertNull($guard->bind('user', 'pass'));
    }

    public function test_bind_allows_null_username_and_password()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with(null, null)->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration);

        $this->assertNull($guard->bind(null, null));
    }

    public function test_bind_always_throws_exception_on_invalid_credentials()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('user', 'pass')->andReturn(false));

        $guard = new Guard($ldap, new DomainConfiguration);

        $this->expectException(BindException::class);

        $guard->bind('user', 'pass');
    }

    public function test_bind_as_administrator()
    {
        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('foo', 'bar')->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration([
            'username' => 'foo',
            'password' => 'bar',
        ]));

        $this->assertNull($guard->bindAsConfiguredUser());
    }

    public function test_binding_events_are_fired_during_bind()
    {
        $events = new Dispatcher();

        $firedBinding = false;
        $firedBound = false;

        $events->listen(Binding::class, function (Binding $event) use (&$firedBinding) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedBinding = true;
        });

        $events->listen(Bound::class, function (Bound $event) use (&$firedBound) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedBound = true;
        });

        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $guard->bind('johndoe', 'secret');

        $this->assertTrue($firedBinding);
        $this->assertTrue($firedBound);
    }

    public function test_auth_events_are_fired_during_attempt()
    {
        $events = new Dispatcher();

        $firedBinding = false;
        $firedBound = false;
        $firedAttempting = false;
        $firedPassed = false;

        $events->listen(Binding::class, function (Binding $event) use (&$firedBinding) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedBinding = true;
        });

        $events->listen(Bound::class, function (Bound $event) use (&$firedBound) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedBound = true;
        });

        $events->listen(Attempting::class, function (Attempting $event) use (&$firedAttempting) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedAttempting = true;
        });

        $events->listen(Passed::class, function (Passed $event) use (&$firedPassed) {
            $this->assertEquals($event->getUsername(), 'johndoe');
            $this->assertEquals($event->getPassword(), 'secret');

            $firedPassed = true;
        });

        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturn(true));

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
        $events = new Dispatcher();

        $totalFired = 0;

        $events->listen('LdapRecord\Auth\Events\*', function () use (&$totalFired) {
            $totalFired++;
        });

        $ldap = (new LdapFake)
            ->expect(LdapFake::operation('bind')->once()->with('johndoe', 'secret')->andReturn(true));

        $guard = new Guard($ldap, new DomainConfiguration);

        $guard->setDispatcher($events);

        $this->assertTrue($guard->attempt('johndoe', 'secret', $bindAsUser = true));
        $this->assertEquals($totalFired, 4);
    }
}
