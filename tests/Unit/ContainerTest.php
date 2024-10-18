<?php

namespace LdapRecord\Tests\Unit;

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

    public function test_set_as_global()
    {
        $container = new Container;

        $container->setAsGlobal();

        $this->assertSame(Container::getInstance(), $container);
    }

    public function test_adding_connections()
    {
        $container = Container::getInstance();

        $container->addConnection($default = new Connection);
        $container->addConnection($other = new Connection, 'other');

        $this->assertSame($default, $container->getDefaultConnection());
        $this->assertSame($other, $container->getConnection('other'));
    }

    public function test_getting_connections()
    {
        $container = Container::getInstance();

        $container->addConnection(new Connection);
        $container->addConnection(new Connection, 'other');

        $this->assertInstanceOf(Connection::class, $container->getDefaultConnection());
        $this->assertInstanceOf(Connection::class, $container->getConnection('default'));
        $this->assertInstanceOf(Connection::class, $container->getConnection('other'));
        $this->assertInstanceOf(Connection::class, Container::getConnection('other'));

        $this->expectException(ContainerException::class);

        $container->getConnection('non-existent');
    }

    public function test_getting_default_connections()
    {
        $container = Container::getInstance();

        $container->addConnection(new Connection);

        $this->assertInstanceOf(Connection::class, $container->getDefaultConnection());
        $this->assertInstanceOf(Connection::class, Container::getConnection('default'));
        $this->assertInstanceOf(Connection::class, Container::getDefaultConnection());
    }

    public function test_getting_default_connection_name()
    {
        $container = Container::getInstance();

        $this->assertEquals('default', $container->getDefaultConnectionName());

        $container->setDefaultConnection('other');

        $this->assertEquals('other', $container->getDefaultConnectionName());
    }

    public function test_setting_default_connections()
    {
        $container = Container::getNewInstance();

        $container->setDefaultConnection('other');

        $container->addConnection(new Connection);

        $this->assertInstanceOf(Connection::class, $container->getConnection('other'));
        $this->assertInstanceOf(Connection::class, $container->getDefaultConnection());

        Container::setDefaultConnection('non-existent');

        $this->expectException(ContainerException::class);

        $container->getDefaultConnection();
    }

    public function test_connection_existence()
    {
        $container = Container::getNewInstance();

        $this->assertFalse($container->hasConnection('default'));

        $container->addConnection(new Connection);

        $this->assertTrue($container->hasConnection('default'));

        $container->addConnection(new Connection, 'other');

        $this->assertTrue($container->hasConnection('other'));
    }

    public function test_removing_connections()
    {
        $container = Container::getNewInstance();

        $container->addConnection(new Connection);
        $container->addConnection(new Connection, 'other');

        $container->removeConnection('non-existent');

        $this->assertInstanceOf(Connection::class, $container->getConnection('default'));
        $this->assertInstanceOf(Connection::class, $container->getConnection('other'));

        $container->removeConnection('other');

        Container::removeConnection('default');
        $this->assertFalse(Container::getInstance()->hasConnection('default'));

        $this->expectException(ContainerException::class);

        $container->getConnection('other');
    }

    public function test_getting_all_connections()
    {
        $container = Container::getNewInstance();

        $connections = [
            'default' => new Connection,
            'other' => new Connection,
        ];

        $container->addConnection($connections['default']);
        $container->addConnection($connections['other'], 'other');

        $this->assertEquals($connections, $container->getConnections());
    }

    public function test_logging_takes_place_after_instance_is_created()
    {
        $container = Container::getInstance();

        $event = new Binding(new Ldap, 'username', 'password');

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
        $this->assertInstanceOf(Dispatcher::class, Container::getInstance()->getDispatcher());
    }

    public function test_setting_container_logger_registers_event_listeners()
    {
        $container = Container::getInstance();

        $dispatcher = $container->getDispatcher();

        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Auth\Events\*'));
        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Query\Events\*'));
        $this->assertCount(0, $dispatcher->getListeners('LdapRecord\Models\Events\*'));

        $container->setLogger(new NullLogger);

        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Auth\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Query\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Models\Events\*'));
    }
}
