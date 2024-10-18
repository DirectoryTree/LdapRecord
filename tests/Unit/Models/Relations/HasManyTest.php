<?php

namespace LdapRecord\Tests\Unit\Models\Relations;

use LdapRecord\Models\Collection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class HasManyTest extends TestCase
{
    public function test_detach_or_delete_parent_with_multiple_results()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('is')->once()->andReturnTrue();
        $model->shouldReceive('delete')->never();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection([$model, $model]));

        $relation->shouldReceive('detach')->with($model)->once();

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_one_result()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('is')->once()->andReturnTrue();
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->once();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection([$model]));
        $relation->shouldReceive('getParent')->once()->andReturn($related);

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_single_non_matching_result()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->never();

        $other = m::mock(Entry::class);
        $other->shouldReceive('is')->once()->with($model)->andReturnFalse();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(
            new Collection([$other])
        );

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_no_results_does_not_delete_parent()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection);

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_all_or_delete_with_missing_relation()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();
        $model->shouldReceive('getRelation')->once()->andReturnNull();

        $relation = m::mock(HasMany::class)->makePartial();

        $relation->shouldReceive('get')->once()->andReturn(
            new Collection([$model])
        );

        $relation->shouldReceive('detach')->once()->with($model);

        $relation->detachAllOrDelete();
    }

    public function test_detach_all_or_delete_with_existing_relation()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->once();

        $subRelation = m::mock(HasMany::class);
        $subRelation->shouldReceive('count')->once()->andReturn(2);

        $model->shouldReceive('getRelation')->once()->andReturn($subRelation);

        $relation = m::mock(HasMany::class)->makePartial();

        $relation->shouldReceive('get')->once()->andReturn(
            new Collection([$model])
        );

        $relation->shouldReceive('detach')->never();

        $relation->detachAllOrDelete();
    }
}
