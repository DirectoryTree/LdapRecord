<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Relations\HasManyIn;

class ModelHasManyInTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_get()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('find')->once()->with('baz')->andReturn(new Entry());

        $model = new ModelHasManyInStub();
        $model->bar = ['baz'];

        $collection = $model->relation($query)->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(new Entry(), $collection->first());
    }

    public function test_get_with_alternate_foreign_key()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('findBy')->once()->with('foreign', 'baz')->andReturn(new Entry());

        $model = new ModelHasManyInStub();
        $model->bar = ['baz'];

        $collection = $model->relation($query, 'foreign')->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(new Entry(), $collection->first());
    }
}

class ModelHasManyInStub extends Model
{
    public function relation($mockBuilder = null, $foreignKey = 'dn')
    {
        $mockBuilder = $mockBuilder ?: m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        return new HasManyIn($mockBuilder, $this, Entry::class, 'bar', $foreignKey, 'relation');
    }
}
