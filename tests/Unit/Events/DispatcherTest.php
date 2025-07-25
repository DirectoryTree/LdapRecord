<?php

namespace LdapRecord\Tests\Unit\Events;

use Exception;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Tests\TestCase;

class DispatcherTest extends TestCase
{
    public function test_event_execution()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo', function ($foo) {
            $_SERVER['__event.test'] = $foo;
        });

        $d->fire('foo', ['bar']);

        $this->assertEquals('bar', $_SERVER['__event.test']);
    }

    public function test_halting_event_execution()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo', function ($foo) {
            $this->assertTrue(true);

            return 'here';
        });

        $d->listen('foo', function ($foo) {
            throw new Exception('should not be called');
        });

        $d->until('foo', ['bar']);
    }

    public function test_wildcard_listeners()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo.bar', function () {
            $_SERVER['__event.test'] = 'regular';
        });

        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'wildcard';
        });

        $d->listen('bar.*', function () {
            $_SERVER['__event.test'] = 'nope';
        });

        $d->fire('foo.bar');

        $this->assertEquals('wildcard', $_SERVER['__event.test']);
    }

    public function test_wildcard_listeners_cache_flushing()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'cached_wildcard';
        });

        $d->fire('foo.bar');

        $this->assertEquals('cached_wildcard', $_SERVER['__event.test']);

        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'new_wildcard';
        });

        $d->fire('foo.bar');

        $this->assertEquals('new_wildcard', $_SERVER['__event.test']);
    }

    public function test_listeners_can_be_removed()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo', function () {
            $_SERVER['__event.test'] = 'foo';
        });

        $d->forget('foo');

        $d->fire('foo');

        $this->assertFalse(isset($_SERVER['__event.test']));
    }

    public function test_wildcard_listeners_can_be_removed()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen('foo.*', function () {
            $_SERVER['__event.test'] = 'foo';
        });

        $d->forget('foo.*');

        $d->fire('foo.bar');

        $this->assertFalse(isset($_SERVER['__event.test']));
    }

    public function test_listeners_can_be_found()
    {
        $d = new Dispatcher;

        $this->assertFalse($d->hasListeners('foo'));

        $d->listen('foo', function () {
            //
        });

        $this->assertTrue($d->hasListeners('foo'));
    }

    public function test_wildcard_listeners_can_be_found()
    {
        $d = new Dispatcher;

        $this->assertFalse($d->hasListeners('foo.*'));

        $d->listen('foo.*', function () {
            //
        });

        $this->assertTrue($d->hasListeners('foo.*'));
    }

    public function test_event_passed_first_to_wildcards()
    {
        $d = new Dispatcher;

        $d->listen('foo.*', function ($event, $data) {
            $this->assertEquals('foo.bar', $event);
            $this->assertEquals(['first', 'second'], $data);
        });

        $d->fire('foo.bar', ['first', 'second']);

        $d = new Dispatcher;

        $d->listen('foo.bar', function ($first, $second) {
            $this->assertEquals('first', $first);
            $this->assertEquals('second', $second);
        });

        $d->fire('foo.bar', ['first', 'second']);
    }

    public function test_classes_work()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen(ExampleEvent::class, function () {
            $_SERVER['__event.test'] = 'baz';
        });

        $d->fire(new ExampleEvent);

        $this->assertSame('baz', $_SERVER['__event.test']);
    }

    public function test_interfaces_work()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen(SomeEventInterface::class, function () {
            $_SERVER['__event.test'] = 'bar';
        });

        $d->fire(new AnotherEvent);

        $this->assertSame('bar', $_SERVER['__event.test']);
    }

    public function test_both_classes_and_interfaces_work()
    {
        unset($_SERVER['__event.test']);

        $d = new Dispatcher;

        $d->listen(AnotherEvent::class, function () {
            $_SERVER['__event.test1'] = 'fooo';
        });

        $d->listen(SomeEventInterface::class, ExampleListener::class);

        $d->fire(new AnotherEvent);

        $this->assertSame('fooo', $_SERVER['__event.test1']);
        $this->assertSame('baar', $_SERVER['__event.test2']);
    }
}

class ExampleEvent {}

class ExampleListener
{
    public function handle(SomeEventInterface $event): void
    {
        $_SERVER['__event.test2'] = 'baar';
    }
}

interface SomeEventInterface {}

class AnotherEvent implements SomeEventInterface {}
