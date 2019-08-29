<?php

namespace LdapRecord\Tests\Connections;

use LdapRecord\Tests\TestCase;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Connections\Container;
use LdapRecord\Connections\Connection;
use LdapRecord\Connections\ContainerException;

class ContainerTest extends TestCase
{
    public function test_get_instance()
    {
        $container = Container::getNewInstance();
        $this->assertInstanceOf(Container::class, $container);
        $this->assertNull($container::getLogger());

        $dispatcher = Container::getEventDispatcher();

        $this->assertInstanceOf(Dispatcher::class, $dispatcher);

        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Auth\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Query\Events\*'));
        $this->assertCount(1, $dispatcher->getListeners('LdapRecord\Models\Events\*'));
    }

    public function test_adding_connections()
    {
        $container = Container::getNewInstance();
        $container->add(new Connection());
        $this->assertInstanceOf(Container::class, $container->add(new Connection(), 'other'));
    }

    public function test_getting_connections()
    {
        $container = Container::getNewInstance();
        $container->add(new Connection());
        $container->add(new Connection(), 'other');

        $this->assertInstanceOf(Connection::class, $container->get());
        $this->assertInstanceOf(Connection::class, $container->get('default'));
        $this->assertInstanceOf(Connection::class, $container->get('other'));

        $this->expectException(ContainerException::class);

        $container->get('non-existent');
    }

    public function test_getting_default_connections()
    {
        $container = Container::getNewInstance();
        $container->add(new Connection());
        $this->assertInstanceOf(Connection::class, $container->getDefault());
    }

    public function test_setting_default_connections()
    {
        $container = Container::getNewInstance();
        $this->assertInstanceOf(Container::class, $container->setDefault('other'));

        $container->add(new Connection());
        $this->assertInstanceOf(Connection::class, $container->get('other'));
        $this->assertInstanceOf(Connection::class, $container->getDefault());

        $container->setDefault('non-existent');
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

        $this->expectException(ContainerException::class);

        $container->get('other');
    }

    public function test_getting_all_connections()
    {
        $container = Container::getNewInstance();
        $connections = [
            'default' => new Connection(),
            'other'   => new Connection(),
        ];

        $container->add($connections['default']);
        $container->add($connections['other'], 'other');

        $this->assertEquals($connections, $container->all());
    }
}
