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
        $model = new ModelHasManyUsingStub();
        $model->setDn('baz');

        $related = m::mock(Entry::class);
        $related->shouldReceive('getDn')->once()->andReturn('foo');

        $this->assertEquals(
            $model->relation()->attach($related),
            $related
        );
        $this->assertEquals(['member' => ['foo']], $model->getAttributes());
    }

    public function test_detach()
    {
        $model = new ModelHasManyUsingStub();
        $model->setDn('baz');
        $model->setAttribute('member', ['foo']);

        $related = m::mock(Entry::class)->makePartial();
        $related->shouldReceive('getDn')->once()->andReturn('foo');

        $this->assertEquals(
            $model->relation()->detach($related),
            $related
        );
        $this->assertEquals(['member' => []], $model->getAttributes());
    }
}

class ModelHasManyUsingStub extends Model
{
    public function relation($mockBuilder = null)
    {
        $mockBuilder = $mockBuilder ?: m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('withoutGlobalScopes')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->withArgs([Entry::class])->andReturnSelf();

        return (new HasMany($mockBuilder, $this, Entry::class, 'foo', 'dn', 'relation'))
            ->using($this, 'member');
    }

    public function save(array $attributes = [])
    {
        return true;
    }
}
