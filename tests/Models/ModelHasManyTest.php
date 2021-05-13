<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Attributes\EscapedValue;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelHasManyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection());
    }

    public function test_relation_name_is_guessed()
    {
        $this->assertEquals('relation', (new ModelHasManyStub())->relation()->getRelationName());
    }

    public function test_get_results()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection());

        $query = $relation->getQuery();
        $query->shouldReceive('escape')->once()->with('foo')->andReturn(new EscapedValue('foo'));
        $query->shouldReceive('getSelects')->once()->withNoArgs()->andReturn(['*']);
        $query->shouldReceive('whereRaw')->once()->with('member', '=', EscapedValue::class)->andReturnSelf();
        $query->shouldReceive('paginate')->once()->with(1000)->andReturn(new Collection([$related = new Entry()]));

        $collection = $relation->getResults();

        $this->assertEquals($related, $collection->first());
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function test_get_recursive_results()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection());

        $related = m::mock(ModelHasManyStub::class);
        $related->shouldReceive('getDn')->andReturn('bar');
        $related->shouldReceive('getAttribute')->once()->with('objectclass')->andReturnNull();
        $related->shouldReceive('convert')->once()->andReturnSelf();
        $related->shouldReceive('relation')->once()->andReturnSelf();
        $related->shouldReceive('recursive')->once()->andReturnSelf();
        $related->shouldReceive('get')->once()->andReturn(new Collection([$child = new Entry()]));

        $query = $relation->getQuery();
        $query->shouldReceive('select')->once();
        $query->shouldReceive('escape')->once()->with('foo')->andReturn(new EscapedValue('foo'));
        $query->shouldReceive('getSelects')->once()->withNoArgs()->andReturn(['*']);
        $query->shouldReceive('whereRaw')->once()->with('member', '=', EscapedValue::class)->andReturnSelf();
        $query->shouldReceive('paginate')->once()->with(1000)->andReturn(new Collection([$related]));

        $results = $relation->recursive()->get();

        $this->assertEquals($related, $results->first());
        $this->assertCount(2, $results);
    }

    public function test_page_size_can_be_set()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection());

        $relation->setPageSize(500);

        $query = $relation->getQuery();
        $query->shouldReceive('escape')->once()->with('foo')->andReturn(new EscapedValue('foo'));
        $query->shouldReceive('getSelects')->once()->withNoArgs()->andReturn(['*']);
        $query->shouldReceive('whereRaw')->once()->with('member', '=', EscapedValue::class)->andReturnSelf();
        $query->shouldReceive('paginate')->once()->with(500)->andReturn(new Collection());

        $this->assertInstanceOf(Collection::class, $relation->getResults());
    }

    public function test_attach()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');

        $related = m::mock(Entry::class);
        $related->shouldReceive('createAttribute')->once()->with('member', 'foo')->andReturnTrue();

        $this->assertEquals($relation->attach($related), $related);

        $related = m::mock(Entry::class);
        $related->shouldReceive('createAttribute')->once()->with('member', 'foo')->andReturnTrue();

        $query = $relation->getQuery();
        $query->shouldReceive('find')->once()->with('bar')->andReturn($related);

        $this->assertEquals($relation->attach('bar'), 'bar');
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');

        $related = m::mock(Entry::class);
        $related->shouldReceive('deleteAttribute')->once()->with(['member' => 'foo'])->andReturnTrue();

        $this->assertEquals($relation->detach($related), $related);

        $related = m::mock(Entry::class);
        $related->shouldReceive('deleteAttribute')->once()->with(['member' => 'foo'])->andReturnTrue();

        $query = $relation->getQuery();
        $query->shouldReceive('find')->once()->with('bar')->andReturn($related);

        $this->assertEquals($relation->detach('bar'), 'bar');
    }

    public function test_detaching_all()
    {
        $relation = $this->getRelation();

        $parent = $relation->getParent();
        $parent->shouldReceive('getDn')->andReturn('foo');
        $parent->shouldReceive('newCollection')->once()->andReturn(new Collection());

        $related = m::mock(Entry::class);
        $related->shouldReceive('getAttribute')->once()->with('objectclass')->andReturnNull();
        $related->shouldReceive('convert')->once()->andReturnSelf();
        $related->shouldReceive('deleteAttribute')->once()->with(['member' => 'foo'])->andReturnTrue();

        $query = $relation->getQuery();
        $query->shouldReceive('select')->once()->with(['*'])->andReturnSelf();
        $query->shouldReceive('escape')->once()->with('foo')->andReturn(new EscapedValue('foo'));
        $query->shouldReceive('getSelects')->once()->withNoArgs()->andReturn(['*']);
        $query->shouldReceive('whereRaw')->once()->with('member', '=', EscapedValue::class)->andReturnSelf();
        $query->shouldReceive('paginate')->once()->with(1000)->andReturn(new Collection([$related]));

        $this->assertEquals($relation->detachAll(), new Collection([$related]));
    }

    protected function getRelation()
    {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        $parent = m::mock(ModelHasManyStub::class);
        $parent->shouldReceive('getConnectionName')->andReturn('default');

        return new HasMany($mockBuilder, $parent, Entry::class, 'member', 'dn', 'relation');
    }
}

class ModelHasManyStub extends Model
{
    public function relation()
    {
        return $this->hasMany(Entry::class, 'foo');
    }
}
