<?php

namespace LdapRecord\Tests\Unit;

use BadMethodCallException;
use LdapRecord\Auth\Events\Binding;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\ContainerException;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Ldap;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ContainerTest extends TestCase
{
    public function test_get_instance()
    {
        $this->assertInstanceOf(Container::class, Container::getInstance());
    }

    public function test_adding_connections()
    {
        $container = Container::getInstance();

        $container->add(new Connection());

        $this->assertInstanceOf(Container::class, $container->add(new Connection(), 'other'));

        Container::getNewInstance();

        Container::addConnection(new Connection(), 'test');

        $this->assertInstanceOf(Connection::class, Container::getConnection('test'));
    }

    public function test_getting_connections()
    {
        $container = Container::getInstance();

        $container->add(new Connection());
        $container->add(new Connection(), 'other');

        $this->assertInstanceOf(Connection::class, $container->get());
        $this->assertInstanceOf(Connection::class, $container->get('default'));
        $this->assertInstanceOf(Connection::class, $container->get('other'));
        $this->assertInstanceOf(Connection::class, Container::getConnection('other'));

        $this->expectException(ContainerException::class);

        $container->get('non-existent');
    }

    public function test_getting_default_connections()
    {
        $container = Container::getInstance();

        $container->add(new Connection());

        $this->assertInstanceOf(Connection::class, $container->getDefault());
        $this->assertInstanceOf(Connection::class, Container::getConnection('default'));
        $this->assertInstanceOf(Connection::class, Container::getDefaultConnection());
    }

    public function test_getting_default_connection_name()
    {
        $container = Container::getInstance();

        $this->assertEquals('default', $container->getDefaultConnectionName());

        $container->setDefault('other');

        $this->assertEquals('other', $container->getDefaultConnectionName());
    }

    public function test_setting_default_connections()
    {
        $container = Container::getNewInstance();

        $this->assertInstanceOf(Container::class, $container->setDefault('other'));

        $container->add(new Connection());

        $this->assertInstanceOf(Connection::class, $container->get('other'));
        $this->assertInstanceOf(Connection::class, $container->getDefault());

        Container::setDefaultConnection('non-existent');

        $this->expectException(ContainerException::class);

        $container->getDefault();
    }

    public function test_connection_existence()
    {
        $container = Container::getNewInstance();

        $this->assertFalse($container->exists('default'));

        $container->add(new Connection());

        $this->assertTrue($container->exists('default'));

        $container->add(new Connection(), 'other');

        $this->assertTrue($container->exists('other'));
    }

    public function test_removing_connections()
    {
        $container = Container::getNewInstance();

        $container->add(new Connection());
        $container->add(new Connection(), 'other');

        $this->assertInstanceOf(Container::class, $container->remove('non-existent'));
        $this->assertInstanceOf(Connection::class, $container->get('default'));
        $this->assertInstanceOf(Connection::class, $container->get('other'));

        $container->remove('other');

        Container::removeConnection('default');
        $this->assertFalse(Container::getInstance()->exists('default'));

        $this->expectException(ContainerException::class);

        $container->get('other');
    }

    public function test_getting_all_connections()
    {
        $container = Container::getNewInstance();

        $connections = [
            'default' => new Connection(),
            'other' => new Connection(),
        ];

        $container->add($connections['default']);
        $container->add($connections['other'], 'other');

        $this->assertEquals($connections, $container->all());
    }

    public function test_logging_takes_place_after_instance_is_created()
    {
        $container = Container::getInstance();

        $event = new Binding(new Ldap(), 'username', 'password');

        $dispatcher = $container->getDispatcher();

        $logger = m::mock(LoggerInterface::class);

        $logger->shouldReceive('info')->once()->with('LDAP () - Operation: Binding - Username: username');

        $container->setLogger($logger);

        $dispatcher->fire($event);
    }

    public function test_event_dispatcher_can_be_retrieved_statically()
    {
        $this->assertInstanceOf(Dispatcher::class, Container::getDispatcher());
    }

    public function test_event_dispatcher_can_be_retrieved_normally()
    {
        $container = Container::getInstance();

        $this->assertInstanceOf(Dispatcher::class, $container->getDispatcher());
    }

    public function test_event_dispatcher_is_set_with_new_instance()
    {
        $container = Container::getInstance();

        $this->assertInstanceOf(Dispatcher::class, $container->getDispatcher());

        $this->assertInstanceOf(Dispatcher::class, $container->dispatcher());
    }

    public function test_setting_container_logger_registers_event_listeners()
    {
        $container = Container::getInstance();

        $dispatcher = $container->getDispatcher();

        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Auth\Events\*'));
        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Query\Events\*'));
        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Models\Events\*'));

        $container->setLogger(new NullLogger());

        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Auth\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Query\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Models\Events\*'));
    }

    public function test_calling_undefined_method_throws_bad_method_call_exception()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method LdapRecord\ConnectionManager::undefined()');

        Container::undefined();
    }
}
