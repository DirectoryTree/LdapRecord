<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasOne;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelHasOneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    public function test_get()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getFirstAttribute')->once()->with('manager')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection([$related = new Entry]));

        $query = $relation->getQuery();
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('find')->once()->with('foo')->andReturn(new Entry);

        $this->assertEquals($related, $relation->get()->first());
    }

    public function test_attach()
    {
        $relation = $this->getRelation();

        $related = new Entry;
        $related->setDn('foo');

        $parent = $relation->getParent();
        $parent->shouldReceive('setAttribute')->once()->with('manager', 'foo')->andReturnSelf();
        $parent->shouldReceive('save')->once()->andReturnTrue();

        $relation->attach($related);
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('setAttribute')->once()->with('manager', null)->andReturnSelf();
        $parent->shouldReceive('save')->once()->andReturnTrue();

        $relation->detach();
    }

    protected function getRelation(): HasOne
    {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->zeroOrMoreTimes()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        $parent = m::mock(ModelHasOneStub::class);
        $parent->shouldReceive('getConnectionName')->andReturn('default');

        return new HasOne($mockBuilder, $parent, Entry::class, 'manager', 'dn');
    }
}

class ModelHasOneStub extends Model
{
    //
}
