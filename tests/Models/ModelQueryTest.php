<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\LdapInterface;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\ContainerException;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\ModelDoesNotExistException;

class ModelQueryTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Flush container instance.
        Container::unsetEventDispatcher();

        // Flush static instance.
        Container::getNewInstance();
    }

    public function test_resolving_connections()
    {
        Container::getNewInstance()->add(new Connection());
        $this->assertInstanceOf(Connection::class, Entry::resolveConnection());

        Container::getNewInstance()->add(new Connection(), 'other');
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
        $query = $model->newQueryWithoutScopes();
        $this->assertEquals($model, $query->getModel());
        $this->assertEquals(['and' => [], 'or' => [], 'raw' => []], $query->filters);
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

    public function test_create()
    {
        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('add')->once()->withArgs(['cn=foo,dc=bar,dc=baz', ['cn' => ['foo'], 'objectclass' => ['bar']]])->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setDn('cn=foo,dc=bar,dc=baz');
        $model->fill(['cn' => 'foo', 'objectclass' => 'bar']);
        $this->assertTrue($model->create());
    }

    public function test_create_without_connection()
    {
        $this->expectException(ContainerException::class);
        (new Entry())->create();
    }

    public function test_create_without_dn()
    {
        $this->expectException(\Exception::class);
        Container::getNewInstance()->add(new Connection());
        (new Entry())->create();
    }

    public function test_create_attribute()
    {
        $ldap = m::mock(LdapInterface::class);
        $ldap->shouldReceive('modAdd')->once()->withArgs(['foo', ['bar' => ['baz']]])->andReturnTrue();

        $query = new Builder($ldap);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($model->createAttribute('bar', 'baz'));
    }

    public function test_create_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->createAttribute('foo', 'bar');
    }

    public function test_update()
    {
        $mod = ['attrib' => 'cn', 'modtype' => 3, 'values' => [0 => 'baz']];

        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('modifyBatch')->once()->withArgs(['foo', [$mod]])->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setRawAttributes(['dn' => 'foo', 'cn' => 'bar']);
        $model->cn = 'baz';

        $this->assertTrue($model->update());
    }

    public function test_update_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->update();
    }

    public function test_update_without_changes()
    {
        $model = (new Entry())->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($model->update());
    }

    public function test_update_attribute()
    {
        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('modReplace')->once()->withArgs(['foo', ['bar' => ['baz']]])->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($model->updateAttribute('bar', 'baz'));
    }

    public function test_update_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->updateAttribute('foo', 'bar');
    }

    public function test_delete()
    {
        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('delete')->once()->withArgs(['foo'])->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($model->delete());
    }

    public function test_delete_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->delete();
    }

    public function test_delete_attribute()
    {
        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('modDelete')->once()->withArgs(['foo', ['bar' => []]])->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($model->deleteAttribute('bar'));
    }

    public function test_delete_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->delete();
    }

    public function test_delete_leaf_nodes()
    {
        $leaf = m::mock(Entry::class);
        $leaf->shouldReceive('delete')->once()->andReturnTrue();

        $shouldBeDeleted = new Collection([$leaf]);

        $query = m::mock(Builder::class);
        $query->shouldReceive('listing')->once()->andReturnSelf();
        $query->shouldReceive('in')->once()->withArgs(['foo'])->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn($shouldBeDeleted);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo', 'cn' => 'bar']);
        $this->assertEquals($shouldBeDeleted, $model->deleteLeafNodes());
        $this->assertFalse($shouldBeDeleted->first()->exists);
    }
}
