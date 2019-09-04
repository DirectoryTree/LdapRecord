<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Relations\BelongsToMany;

class ModelBelongsToManyTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_relation_name_is_guessed()
    {
        $this->assertEquals(
            'relation',
            (new ModelBelongsToManyStub())->relation(m::mock(Builder::class))->getRelationName()
        );
    }

    public function test_get_results()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('escape')->once()->withArgs(['bar', '', 2])->andReturn('bar');
        $query->shouldReceive('whereRaw')->once()->withArgs(['foo', '=', 'bar'])->andReturnSelf();
        $query->shouldReceive('paginate')->once()->withNoArgs()->andReturn(new Collection([new Entry()]));

        $model = (new ModelBelongsToManyStub())->setRawAttributes(['dn' => 'bar']);
        $relation = $model->relation($query);

        $collection = $relation->getResults();

        $this->assertEquals(new Entry(), $collection->first());
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function test_get_recursive_results()
    {
        $related = m::mock(Model::class);
        $related->shouldReceive('getDn')->twice()->andReturn('baz');
        $related->shouldReceive('relation')->once()->withNoArgs()->andReturnSelf();
        $related->shouldReceive('get')->once()->andReturn(new Collection());
        $related->shouldReceive('getAttribute')->once()->withArgs(['objectclass'])->andReturnNull();
        $related->shouldReceive('convert')->once()->andReturnSelf();

        $query = m::mock(Builder::class);
        $query->shouldReceive('escape')->once()->withArgs(['bar', '', 2])->andReturn('bar');
        $query->shouldReceive('whereRaw')->once()->withArgs(['foo', '=', 'bar'])->andReturnSelf();
        $query->shouldReceive('paginate')->once()->withNoArgs()->andReturn(new Collection([$related]));

        $model = (new ModelBelongsToManyStub())->setRawAttributes(['dn' => 'bar']);
        $relation = $model->relation($query);

        $collection = $relation->recursive()->getResults();

        $this->assertEquals($related, $collection->first());
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function test_attach()
    {
        $model = new ModelBelongsToManyStub();
        $model->setDn('baz');

        $related = m::mock(Entry::class);
        $related->shouldReceive('getAttribute')->once()->withArgs(['foo'])->andReturnNull();
        $related->shouldReceive('setAttribute')->once()->withArgs(['foo', ['baz']])->andReturnSelf();
        $related->shouldReceive('save')->once()->withNoArgs()->andReturnSelf();

        $this->assertEquals(
            $model->relation()->attach($related),
            $related
        );
    }

    public function test_attach_with_already_attached_model()
    {
        $model = new ModelBelongsToManyStub();
        $model->setDn('baz');

        $related = m::mock(Entry::class);
        $related->shouldReceive('getAttribute')->once()->withArgs(['foo'])->andReturn(['baz']);
        $related->shouldNotReceive('setAttribute');

        $this->assertEquals(
            $model->relation()->attach($related),
            $related
        );
    }

    public function test_detach()
    {
        $model = new ModelBelongsToManyStub();
        // This DN will be missing from the below setAttribute call
        // since we are detaching it from the related model.
        $model->setDn('baz');

        $related = m::mock(Entry::class)->makePartial();
        $related->foo = ['baz', 'bar'];
        $related->shouldReceive('setAttribute')->once()->withArgs(['foo', [1 => 'bar']])->andReturnSelf();
        $related->shouldReceive('save')->once()->withNoArgs()->andReturnSelf();

        $this->assertEquals(
            $model->relation()->detach($related),
            $related
        );
    }

    public function test_detaching_all_related_models()
    {
        $model = new ModelBelongsToManyStub();
        $model->setDn('baz');

        $related = m::mock(Entry::class);
        $related->shouldReceive('getAttribute')->once()->withArgs(['foo'])->andReturn(['baz']);
        $related->shouldReceive('getAttribute')->once()->withArgs(['objectclass'])->andReturnNull();
        $related->shouldReceive('convert')->once()->andReturnSelf();
        $related->shouldReceive('setAttribute')->once()->withArgs(['foo', []])->andReturnSelf();
        $related->shouldReceive('save')->once()->withNoArgs()->andReturnSelf();

        $query = m::mock(Builder::class);
        $query->shouldReceive('select')->once()->withArgs([['*']])->andReturnSelf();
        $query->shouldReceive('escape')->once()->withArgs(['baz', '', 2])->andReturn('baz');
        $query->shouldReceive('whereRaw')->once()->withArgs(['foo', '=', 'baz'])->andReturnSelf();
        $query->shouldReceive('paginate')->once()->withNoArgs()->andReturn(new Collection([$related]));

        $this->assertEquals(
            $model->relation($query)->detach(),
            new Collection([$related])
        );
    }
}

class ModelBelongsToManyStub extends Model
{
    public function relation($mockBuilder = null)
    {
        $mockBuilder = $mockBuilder ?: m::mock(Builder::class);
        $mockBuilder->shouldReceive('clearFilters')->once()->withNoArgs()->andReturnSelf();
        $mockBuilder->shouldReceive('setModel')->once()->withArgs([Entry::class])->andReturnSelf();

        return new BelongsToMany($mockBuilder, $this, Entry::class, 'foo', 'dn', 'relation');
    }
}
