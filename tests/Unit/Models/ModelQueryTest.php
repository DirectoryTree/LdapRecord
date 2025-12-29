<?php

namespace LdapRecord\Tests\Unit\Models;

use Closure;
use DateTime;
use Exception;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\ContainerException;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use UnexpectedValueException;

class ModelQueryTest extends TestCase
{
    public function test_resolving_connections()
    {
        Container::addConnection(new Connection);

        $this->assertInstanceOf(Connection::class, Entry::resolveConnection());

        Container::addConnection(new Connection, 'other');

        $model = new Entry;

        $model->setConnection('other');

        $this->assertInstanceOf(Connection::class, $model::resolveConnection('other'));
        $this->assertInstanceOf(Connection::class, $model->getConnection());
    }

    public function test_new_query()
    {
        Container::addConnection(new Connection);

        $model = new Entry;

        $this->assertEquals($model, Entry::query()->getModel());
        $this->assertEquals($model, $model->newQuery()->getModel());
    }

    public function test_new_query_without_scopes()
    {
        Container::addConnection(new Connection);

        $model = new Entry;

        $query = $model->newQueryWithoutScopes();

        $this->assertEquals($model, $query->getModel());
        $this->assertNull($query->getQuery()->getFilter());
    }

    public function test_new_query_has_connection_base_dn()
    {
        Container::addConnection(
            new Connection(['base_dn' => 'foo'])
        );

        $this->assertEquals('foo', Entry::query()->getBaseDn());
    }

    public function test_creating_new_query_without_connection_fails()
    {
        $this->expectException(ContainerException::class);

        Entry::query();
    }

    public function test_new_queries_apply_object_class_scopes()
    {
        Container::addConnection(new Connection);

        // Scopes are wrapped in their own AndGroup for isolation
        $this->assertEquals(
            '(&(objectclass=foo)(objectclass=bar)(objectclass=baz))',
            ModelWithObjectClassStub::query()->getUnescapedQuery()
        );
    }

    public function test_find_queries_substitute_base_dn()
    {
        Container::addConnection(
            new Connection(['base_dn' => 'dc=foo,dc=bar'])
        );

        DirectoryFake::setup()->getLdapConnection()->expect(
            [
                LdapFake::operation('read')->once()->with('cn=John Doe,dc=foo,dc=bar')->andReturn([
                    ['dn' => 'cn=John Doe,dc=foo,dc=bar'],
                ]),
            ]
        );

        $this->assertEquals('cn=John Doe,dc=foo,dc=bar', Entry::find('cn=John Doe,{base}')->getDn());
    }

    public function test_on()
    {
        Container::addConnection(new Connection, 'other');

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
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('add')
                ->once()
                ->with(
                    'cn=foo,dc=bar,dc=baz',
                    ['cn' => ['foo'], 'objectclass' => ['bar']]
                )
                ->andReturnTrue()
        );

        $model = (new Entry)
            ->setDn('cn=foo,dc=bar,dc=baz')
            ->fill(['cn' => 'foo', 'objectclass' => 'bar']);

        $model->save();

        $this->assertEquals($model->getOriginal(), $model->getAttributes());

        $this->assertTrue($model->wasRecentlyCreated);
    }

    public function test_create_without_connection()
    {
        $this->expectException(ContainerException::class);

        (new Entry)->create();
    }

    public function test_create_without_dn()
    {
        $this->expectException(Exception::class);

        Container::addConnection(new Connection);

        (new Entry)->create();
    }

    public function test_create_with_substituted_base()
    {
        Container::addConnection(
            new Connection(['base_dn' => 'dc=foo,dc=bar'])
        );

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('add')->once()->with('cn=foo,dc=foo,dc=bar')->andReturnTrue(),
        );

        $model = new Entry;

        $model->setDn('cn=foo,{base}');
        $model->fill(['cn' => 'foo', 'objectclass' => 'bar']);

        $model->save();

        $this->assertEquals($model->getOriginal(), $model->getAttributes());

        $this->assertTrue($model->wasRecentlyCreated);
    }

    public function test_add_attribute()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('modAdd')->once()->with('foo', ['bar' => ['baz']])->andReturnTrue()
        );

        $model = (new Entry)->setRawAttributes(['dn' => 'foo']);

        $model->addAttribute('bar', 'baz');
    }

    public function test_add_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);

        $model = new Entry;

        $model->addAttribute('foo', 'bar');
    }

    public function test_update()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('modifyBatch')
                ->once()
                ->with('foo', [[
                    'attrib' => 'cn',
                    'modtype' => 3,
                    'values' => [0 => 'baz'],
                ]])
                ->andReturnTrue()
        );

        $model = (new Entry)->setRawAttributes([
            'dn' => 'foo',
            'cn' => 'bar',
        ]);

        $model->update(['cn' => 'baz']);

        $this->assertEmpty($model->getModifications());
        $this->assertFalse($model->wasRecentlyCreated);
    }

    public function test_update_without_existing_model()
    {
        Container::addConnection(new Connection);

        $this->expectException(ModelDoesNotExistException::class);

        (new Entry)->update();
    }

    public function test_update_without_changes_does_not_attempt_update()
    {
        $model = m::mock(Entry::class)->makePartial();

        $model->shouldNotReceive('newQuery');

        $model->setRawAttributes(['dn' => 'foo']);

        $model->update();
    }

    public function test_replace_attribute()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('modReplace')
                ->once()
                ->with(['foo', ['bar' => ['baz']]])
                ->andReturnTrue()
        );

        $model = (new Entry)->setRawAttributes(['dn' => 'foo']);

        $model->replaceAttribute('bar', 'baz');
    }

    public function test_replace_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);

        $model = new Entry;

        $model->replaceAttribute('foo', 'bar');
    }

    public function test_delete()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('delete')
                ->once()
                ->with('foo')
                ->andReturnTrue()
        );

        $model = (new Entry)->setRawAttributes(
            ['dn' => 'foo', 'foo' => ['bar']]
        );

        $model->delete();

        $this->assertEquals(['foo' => ['bar']], $model->getAttributes());
    }

    public function test_delete_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);

        $model = new Entry;

        $model->delete();
    }

    public function test_remove_attribute()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect([
            LdapFake::operation('modDelete')->once()->with('dn', ['foo' => []])->andReturnTrue(),
            LdapFake::operation('modDelete')->once()->with('dn', ['bar' => ['zal']])->andReturnTrue(),
        ]);

        $model = (new Entry)->setRawAttributes([
            'dn' => 'dn',
            'foo' => ['bar'],
            'bar' => ['baz', 'zal', 'zar'],
        ]);

        $model->removeAttributes('foo');

        $this->assertEquals(['bar' => ['baz', 'zal', 'zar']], $model->getAttributes());
        $this->assertEquals(['bar' => ['baz', 'zal', 'zar']], $model->getOriginal());

        $model->removeAttributes(['bar' => ['zal']]);

        $this->assertEquals(['bar' => ['baz', 'zar']], $model->getAttributes());
        $this->assertEquals(['bar' => ['baz', 'zar']], $model->getOriginal());
    }

    public function test_remove_attribute_with_array()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('modDelete')->once()->with('dn', ['foo' => [], 'bar' => ['zar']])->andReturnTrue(),
        );

        $model = (new Entry)->setRawAttributes(['dn' => 'dn']);

        $model->removeAttributes(['foo', 'bar' => 'zar']);
    }

    public function test_delete_attribute_without_existing_model()
    {
        $this->expectException(ModelDoesNotExistException::class);

        $model = new Entry;

        $model->delete();
    }

    public function test_delete_leaf_nodes()
    {
        $leaf = m::mock(Entry::class);

        $leaf->shouldReceive('delete')->once()->andReturnTrue();

        $query = m::mock(Builder::class);
        $query->shouldReceive('list')->once()->andReturnSelf();
        $query->shouldReceive('in')->once()->with('foo')->andReturnSelf();
        $query->shouldReceive('each')->once()->with(m::on(function ($callback) use ($leaf) {
            $callback($leaf);

            return $callback instanceof Closure;
        }));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQueryWithoutScopes')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'foo', 'cn' => 'bar']);
        $this->assertNull($model->deleteLeafNodes());
        $this->assertFalse($leaf->exists);
    }

    public function test_destroy()
    {
        Container::addConnection(new Connection);

        $this->assertEquals(1, ModelDestroyStub::destroy('foo'));
        $this->assertEquals(2, ModelDestroyStub::destroy(['foo', 'bar']));
        $this->assertEquals(2, ModelDestroyStub::destroy(new Collection([
            new ModelDestroyStub(['dn' => 'foo']),
            new ModelDestroyStub(['dn' => 'bar']),
        ])));
    }

    public function test_descendants_scope()
    {
        Container::addConnection(new Connection);

        $model = new Entry;
        $model->setDn('ou=Users,dc=acme,dc=org');

        $query = $model->descendants();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('ou=Users,dc=acme,dc=org', $query->getDn());
        $this->assertEquals('list', $query->getType());
    }

    public function test_ancestors_scope()
    {
        Container::addConnection(new Connection);

        $model = new Entry;
        $model->setDn('ou=Office,ou=Users,dc=acme,dc=org');

        $query = $model->ancestors();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('dc=acme,dc=org', $query->getDn());
        $this->assertEquals('list', $query->getType());
    }

    public function test_siblings_scope()
    {
        Container::addConnection(new Connection);

        $model = new Entry;
        $model->setDn('ou=Users,dc=acme,dc=org');

        $query = $model->siblings();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('dc=acme,dc=org', $query->getDn());
        $this->assertEquals('list', $query->getType());
    }

    public function test_all()
    {
        Container::addConnection(new Connection);

        $this->assertInstanceOf(Collection::class, ModelAllTest::all());
    }

    public function test_date_objects_are_converted_to_ldap_timestamps_in_where_clause()
    {
        Container::addConnection(new Connection);

        $datetime = new DateTime;

        $query = ModelQueryDateConversionTest::query()
            ->whereRaw('standard', '=', $datetime)
            ->whereRaw('windows', '=', $datetime)
            ->whereRaw('windowsinteger', '=', $datetime);

        $ldapTimestamp = (new Timestamp('ldap'))->fromDateTime($datetime);
        $windowsTimestamp = (new Timestamp('windows'))->fromDateTime($datetime);
        $windowsIntTimestamp = (new Timestamp('windows-int'))->fromDateTime($datetime);

        // Check that the filter string contains the converted timestamps
        $filterString = (string) $query->getQuery()->getFilter();
        $this->assertStringContainsString("(standard={$ldapTimestamp})", $filterString);
        $this->assertStringContainsString("(windows={$windowsTimestamp})", $filterString);
        $this->assertStringContainsString("(windowsinteger={$windowsIntTimestamp})", $filterString);
    }

    public function test_exception_is_thrown_when_date_objects_cannot_be_converted()
    {
        Container::addConnection(new Connection);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot convert attribute [non-existent-date]');

        Entry::query()->where('non-existent-date', new DateTime);
    }
}

class ModelWithObjectClassStub extends Model
{
    public static array $objectClasses = ['foo', 'bar', 'baz'];
}

class ModelQueryDateConversionTest extends Model
{
    protected array $dates = [
        'standard' => 'ldap',
        'windows' => 'windows',
        'windowsinteger' => 'windows-int',
    ];
}

class ModelAllTest extends Model
{
    public static function query(): Builder
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('paginate')->once()->withNoArgs()->andReturn(new Collection);

        return $query;
    }
}

class ModelDestroyStub extends Model
{
    public static function find($dn, $attributes = []): Model|Collection|null
    {
        $stub = m::mock(Entry::class);
        $stub->shouldReceive('delete')->once()->andReturnTrue();

        return $stub;
    }
}
