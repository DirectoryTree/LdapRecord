<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Relations\HasMany;

class ModelHasManyUsingTest extends TestCase
{
    public function test_attach()
    {
        $relation = $this->getRelation();

        $using = $relation->getParent();
        $using->shouldReceive('getDn')->once()->andReturn('foo');
        $using->shouldReceive('createAttribute')->once()->with('member', 'foo')->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry();
        $related->setRawAttributes(['dn' => 'foo']);

        $this->assertEquals($relation->attach($related), $related);
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $using = $relation->getParent();
        $using->shouldReceive('getDn')->once()->andReturn('foo');
        $using->shouldReceive('deleteAttribute')->once()->with(['member' => 'foo'])->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry();
        $related->setRawAttributes(['dn' => 'foo']);

        $this->assertEquals($relation->detach($related), $related);
    }

    protected function getRelation()
    {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->with(Entry::class)->andReturnSelf();

        $parent = m::mock(ModelHasManyUsingStub::class);
        $parent->shouldReceive('getConnectionName')->andReturn('default');

        return new HasMany($mockBuilder, $parent, Entry::class, 'member', 'dn', 'relation');
    }
}

class ModelHasManyUsingStub extends Model
{
    //
}
