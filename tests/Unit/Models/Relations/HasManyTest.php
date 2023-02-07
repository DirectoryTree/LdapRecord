<?php

namespace LdapRecord\Tests\Unit\Models\Relations;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class HasManyTest extends TestCase
{
    public function test_detach_or_delete_parent_with_multiple_results()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('count')->withNoArgs()->andReturn(2);
        $relation->shouldReceive('detach')->with($model)->once();

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_one_result()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->once();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('count')->withNoArgs()->andReturn(1);
        $relation->shouldReceive('getParent')->once()->andReturn($related);

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_no_results()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->once();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('count')->withNoArgs()->andReturn(0);
        $relation->shouldReceive('getParent')->once()->andReturn($related);

        $relation->detachOrDeleteParent($model);
    }
}
