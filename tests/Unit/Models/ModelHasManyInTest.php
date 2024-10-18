<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasManyIn;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelHasManyInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    public function test_get()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('find')->once()->with('baz')->andReturn(new Entry);

        $model = new ModelHasManyInStub;
        $model->bar = ['baz'];

        $collection = $model->relation($query)->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(new Entry, $collection->first());
    }

    public function test_get_with_alternate_foreign_key()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('findBy')->once()->with('foreign', 'baz')->andReturn(new Entry);

        $model = new ModelHasManyInStub;
        $model->bar = ['baz'];

        $collection = $model->relation($query, 'foreign')->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(new Entry, $collection->first());
    }
}

class ModelHasManyInStub extends Model
{
    public function relation($mockBuilder = null, $foreignKey = 'dn'): HasManyIn
    {
        $mockBuilder = $mockBuilder ?: m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->twice()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        return new HasManyIn($mockBuilder, $this, Entry::class, 'bar', $foreignKey, 'relation');
    }
}
