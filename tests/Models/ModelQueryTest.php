<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Query\Builder;
use LdapRecord\Tests\TestCase;
use LdapRecord\Connections\Container;
use LdapRecord\Connections\Connection;
use LdapRecord\Connections\ContainerException;

class ModelQueryTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Flush static instance.
        Container::getNewInstance();
    }

    public function test_resolving_connections()
    {
        Container::getNewInstance()->add(new Connection());
        $this->assertInstanceOf(Connection::class, Entry::resolveConnection());

        Container::getNewInstance()->add(new Connection(),'other');
        $model = new Entry();
        $model->setConnection('other');
        $this->assertInstanceOf(Connection::class, $model::resolveConnection('other'));
        $this->assertInstanceOf(Connection::class, $model->getConnection());
    }

    public function test_new_query()
    {
        Container::getNewInstance()->add(new Connection());

        $model = new Entry();
        $this->assertEquals($model, Entry::query()->getModel());
        $this->assertEquals($model, $model->newQuery()->getModel());
    }

    public function test_new_query_without_scopes()
    {
        Container::getNewInstance()->add(new Connection());
        $model = new Entry();
        $this->assertNull($model->newQueryWithoutScopes()->getModel());
    }

    public function test_new_query_has_connection_base_dn()
    {
        Container::getNewInstance()->add(new Connection(['base_dn' => 'foo']));
        $this->assertEquals('foo', Entry::query()->getDn());
    }

    public function test_new_query_without_connection()
    {
        $this->expectException(ContainerException::class);
        Entry::query();
    }

    public function test_new_queries_apply_object_class_scopes()
    {
        Container::getNewInstance()->add(new Connection());

        Entry::$objectClasses = ['foo', 'bar', 'baz'];

        $this->assertEquals(
            '(&(objectclass=foo)(objectclass=bar)(objectclass=baz))',
            Entry::query()->getUnescapedQuery()
        );
    }

    public function test_on()
    {
        Container::getNewInstance()->add(new Connection(), 'other');

        $query = Entry::on('other');
        $this->assertInstanceOf(Builder::class, $query);
    }

    public function test_on_without_connection()
    {
        $this->expectException(ContainerException::class);
        Entry::on('other');
    }
}
