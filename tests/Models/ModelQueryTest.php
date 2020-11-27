<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\ContainerException;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Tests\CreatesConnectedLdapMocks;
use LdapRecord\Models\ModelDoesNotExistException;

class ModelQueryTest extends TestCase
{
    use CreatesConnectedLdapMocks;

    protected function setUp() : void
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

    public function test_creating_new_query_without_connection_fails()
    {
        $this->expectException(ContainerException::class);
        Entry::query();
    }

    public function test_new_queries_apply_object_class_scopes()
    {
        Container::getNewInstance()->add(new Connection());

        $this->assertEquals(
            '(&(objectclass=foo)(objectclass=bar)(objectclass=baz))',
            ModelWithObjectClassStub::query()->getUnescapedQuery()
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
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('add')->once()->with('cn=foo,dc=bar,dc=baz', ['cn' => ['foo'], 'objectclass' => ['bar']])->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setDn('cn=foo,dc=bar,dc=baz');
        $model->fill($attributes = ['cn' => 'foo', 'objectclass' => 'bar']);

        $model->save();

        $this->assertEquals($model->getOriginal(), $model->getAttributes());
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
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('modAdd')->once()->with('foo', ['bar' => ['baz']])->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo']);
        $model->createAttribute('bar', 'baz');
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

        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('modifyBatch')->once()->with('foo', [$mod])->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes([
            'dn' => 'foo',
            'cn' => 'bar',
        ]);

        $model->update(['cn' => 'baz']);
    }

    public function test_update_without_existing_model()
    {
        Container::addConnection(new Connection());
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry(['objectclass' => 'foo', 'bar', 'cn' => 'baz']);
        $model->update();
    }

    public function test_update_without_changes_does_not_attempt_update()
    {
        $model = m::mock(Entry::class)->makePartial();
        $model->shouldNotReceive('newQuery');

        $model->setRawAttributes(['dn' => 'foo']);

        $model->update();
    }

    public function test_update_attribute()
    {
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('modReplace')->once()->with('foo', ['bar' => ['baz']])->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo']);

        $model->updateAttribute('bar', 'baz');
    }

    public function test_update_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->updateAttribute('foo', 'bar');
    }

    public function test_delete()
    {
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('delete')->once()->with('foo')->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo', 'foo' => ['bar']]);

        $model->delete();

        $this->assertEquals(['foo' => ['bar']], $model->getAttributes());
    }

    public function test_delete_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);
        $model = new Entry();
        $model->delete();
    }

    public function test_delete_attribute()
    {
        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('modDelete')->once()->with('dn', ['foo' => []]);
        $ldap->shouldReceive('modDelete')->once()->with('dn', ['bar' => ['zal']]);

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->twice()->andReturn($query);

        $model->setRawAttributes([
            'dn' => 'dn',
            'foo' => ['bar'],
            'bar' => ['baz', 'zal', 'zar'],
        ]);

        $model->deleteAttribute('foo');

        $this->assertEquals(['bar' => ['baz', 'zal', 'zar']], $model->getAttributes());
        $this->assertEquals(['bar' => ['baz', 'zal', 'zar']], $model->getOriginal());

        $model->deleteAttribute(['bar' => ['zal']]);

        $this->assertEquals(['bar' => ['baz', 'zar']], $model->getAttributes());
        $this->assertEquals(['bar' => ['baz', 'zar']], $model->getOriginal());
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
        $query->shouldReceive('in')->once()->with('foo')->andReturnSelf();
        $query->shouldReceive('paginate')->once()->andReturn($shouldBeDeleted);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQueryWithoutScopes')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo', 'cn' => 'bar']);
        $this->assertEquals($shouldBeDeleted, $model->deleteLeafNodes());
        $this->assertFalse($shouldBeDeleted->first()->exists);
    }

    public function test_destroy()
    {
        Container::getNewInstance()->add(new Connection());

        $this->assertEquals(1, ModelDestroyStub::destroy('foo'));
        $this->assertEquals(2, ModelDestroyStub::destroy(['foo', 'bar']));
        $this->assertEquals(2, ModelDestroyStub::destroy(new Collection(['foo', 'bar'])));
    }

    public function test_descendants_scope()
    {
        Container::getNewInstance()->add(new Connection());

        $model = new Entry();
        $model->setDn('ou=Users,dc=acme,dc=org');

        $query = $model->descendants();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('ou=Users,dc=acme,dc=org', $query->getDn());
        $this->assertEquals('listing', $query->getType());
    }

    public function test_ancestors_scope()
    {
        Container::getNewInstance()->add(new Connection());

        $model = new Entry();
        $model->setDn('ou=Office,ou=Users,dc=acme,dc=org');

        $query = $model->ancestors();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('dc=acme,dc=org', $query->getDn());
        $this->assertEquals('listing', $query->getType());
    }

    public function test_siblings_scope()
    {
        Container::getNewInstance()->add(new Connection());

        $model = new Entry();
        $model->setDn('ou=Users,dc=acme,dc=org');

        $query = $model->siblings();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('dc=acme,dc=org', $query->getDn());
        $this->assertEquals('listing', $query->getType());
    }

    public function test_all()
    {
        Container::getNewInstance()->add(new Connection());

        $this->assertInstanceOf(Collection::class, ModelAllTest::all());
    }

    public function test_date_objects_are_converted_to_ldap_timestamps_in_where_clause()
    {
        Container::getNewInstance()->add(new Connection());

        $datetime = new \DateTime();

        $query = ModelQueryDateConversionTest::query()->newInstance()
            ->whereRaw('standard', '=', $datetime)
            ->whereRaw('windows', '=', $datetime)
            ->whereRaw('windowsinteger', '=', $datetime);

        $this->assertEquals((new Timestamp('ldap'))->fromDateTime($datetime), $query->filters['and'][0]['value']);
        $this->assertEquals((new Timestamp('windows'))->fromDateTime($datetime), $query->filters['and'][1]['value']);
        $this->assertEquals((new Timestamp('windows-int'))->fromDateTime($datetime), $query->filters['and'][2]['value']);
    }

    public function test_exception_is_thrown_when_date_objects_cannot_be_converted()
    {
        Container::getNewInstance()->add(new Connection());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot convert field [non-existent-date]');

        Entry::query()->where('non-existent-date', new \DateTime());
    }
}

class ModelWithObjectClassStub extends Model
{
    public static $objectClasses = ['foo', 'bar', 'baz'];
}

class ModelQueryDateConversionTest extends Model
{
    protected $dates = [
        'standard'       => 'ldap',
        'windows'        => 'windows',
        'windowsinteger' => 'windows-int',
    ];
}

class ModelAllTest extends Model
{
    public static function query()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('paginate')->once()->withNoArgs()->andReturn(new Collection());

        return $query;
    }
}

class ModelDestroyStub extends Model
{
    public function find($dn, $columns = [])
    {
        $stub = m::mock(Entry::class);
        $stub->shouldReceive('delete')->once()->andReturnTrue();

        return $stub;
    }
}
