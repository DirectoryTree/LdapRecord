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
        $using->shouldReceive('addAttribute')->once()->with('member', 'foo')->andReturnSelf();
        $using->shouldReceive('addAttribute')->once()->with('member', 'bar')->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry;
        $related->setRawAttributes(['dn' => 'foo']);

        $relation->attach($related);
        $relation->attach('bar');
    }

    public function test_detach()
    {
        $relation = $this->getRelation();

        $using = $relation->getParent();
        $using->shouldReceive('removeAttribute')->once()->with('member', 'foo')->andReturnSelf();
        $using->shouldReceive('removeAttribute')->once()->with('member', 'bar')->andReturnSelf();

        $relation->using($relation->getParent(), 'member');

        $related = new Entry;
        $related->setRawAttributes(['dn' => 'foo']);

        $relation->detach($related);
        $relation->detach('bar');
    }

    protected function getRelation(): HasMany
    {
        $mockBuilder = m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->zeroOrMoreTimes()->withNoArgs()->andReturnSelf();
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
