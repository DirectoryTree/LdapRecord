<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelHasManyUsingTest extends TestCase
{
    public function test_attach()
    {
        $relation = $this->getRelation();

        $using = $relation->getParent();
        $using->shouldReceive('createAttribute')->once()->with('member', 'foo')->andReturnSelf();
        $using->shouldReceive('createAttribute')->once()->with('member', 'bar')->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry();
        $related->setRawAttributes(['dn' => 'foo']);

        $this->assertEquals($relation->attach($related), $related);
        $this->assertEquals($relation->attach('bar'), 'bar');
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $using = $relation->getParent();
        $using->shouldReceive('deleteAttribute')->once()->with(['member' => 'foo'])->andReturnSelf();
        $using->shouldReceive('deleteAttribute')->once()->with(['member' => 'bar'])->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry();
        $related->setRawAttributes(['dn' => 'foo']);

        $this->assertEquals($relation->detach($related), $related);
        $this->assertEquals($relation->detach('bar'), 'bar');
    }

    protected function getRelation(): HasMany
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
