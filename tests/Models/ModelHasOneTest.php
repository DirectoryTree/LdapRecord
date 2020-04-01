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
use LdapRecord\Models\Relations\HasOne;

class ModelHasOneTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_get()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->withArgs([['*']])->andReturnSelf();
        $query->shouldReceive('find')->once()->withArgs(['baz'])->andReturn(new Entry());

        $model = new ModelHasOneStub();
        $model->bar = ['baz'];

        $collection = $model->relation($query)->get();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(new Entry(), $collection->first());
    }

    public function test_attach()
    {
        $model = new ModelHasOneStub();

        $related = new Entry();
        $related->setDn('dn');

        $this->assertEquals(
            $related,
            $model->relation()->attach($related)
        );
        $this->assertEquals(['bar' => ['dn']], $model->getDirty());
    }

    public function test_detach()
    {
        $model = new ModelHasOneStub();
        $model->bar = ['dn'];

        $model->relation()->detach();
        $this->assertEquals([], $model->bar);

        $this->assertEquals(['bar' => []], $model->getDirty());
    }
}

class ModelHasOneStub extends Model
{
    public function relation($mockBuilder = null, $foreignKey = 'dn')
    {
        $mockBuilder = $mockBuilder ?: m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->withArgs([Entry::class])->andReturnSelf();

        return new HasOne($mockBuilder, $this, Entry::class, 'bar', $foreignKey);
    }

    public function save(array $attributes = [])
    {
        return true;
    }
}
