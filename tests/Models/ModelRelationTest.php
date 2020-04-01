<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Relations\Relation;

class ModelRelationTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_has_parent()
    {
        $this->assertInstanceOf(
            ModelRelationTestStub::class,
            (new ModelRelationTestStub())->relation()->getParent()
        );
    }

    public function test_has_relations()
    {
        $this->assertEquals(
            [RelatedModelTestStub::class],
            (new ModelRelationTestStub())->relation()->getRelated()
        );
    }

    public function test_has_query()
    {
        $this->assertInstanceOf(
            Builder::class,
            (new ModelRelationTestStub())->relation()->getQuery()
        );
    }

    public function test_query_has_no_filters()
    {
        $this->assertEquals(
            ['and' => [], 'or' => [], 'raw' => []],
            (new ModelRelationTestStub())->relation()->getQuery()->filters
        );
    }

    public function test_query_has_default_model()
    {
        $this->assertInstanceOf(
            Entry::class,
            (new ModelRelationTestStub())->relation()->getQuery()->getModel()
        );
    }

    public function test_has_related_key()
    {
        $this->assertEquals(
            'foo',
            (new ModelRelationTestStub())->relation()->getRelationKey()
        );
    }

    public function test_has_foreign_key()
    {
        $this->assertEquals(
            'bar',
            (new ModelRelationTestStub())->relation()->getForeignKey()
        );
    }

    public function test_get()
    {
        $collection = (new ModelRelationTestStub())->relation()->get('foo');

        $this->assertEmpty($collection);
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function test_get_results()
    {
        $relation = (new ModelRelationTestStub())->relation();
        $collection = $relation->get('foo');

        $this->assertEmpty($collection);
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(['foo', 'objectclass'], $relation->getQuery()->getSelects());
    }

    public function test_parent_model_scope_is_removed_from_relation_query()
    {
        $relation = (new ModelRelationWithScopeTestStub())->relation();

        $query = $relation->getRelationQuery();

        $executedQuery = $query->getQuery();

        $this->assertEmpty($query->appliedScopes());
        $this->assertEquals([ModelRelationScopeTestStub::class], $query->removedScopes());
        $this->assertEquals('(foo=)', $executedQuery);
    }
}

class RelationTestStub extends Relation
{
    public function getResults()
    {
        return $this->parent->newCollection();
    }
}

class RelatedModelTestStub extends Model
{
    public static $objectClasses = ['foo', 'bar'];
}

class ModelRelationTestStub extends Model
{
    public function relation()
    {
        return new RelationTestStub($this->newQuery(), $this, RelatedModelTestStub::class, 'foo', 'bar');
    }
}

class ModelRelationWithScopeTestStub extends Model
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(new ModelRelationScopeTestStub());
    }

    public function relation()
    {
        return $this->hasMany(self::class, 'foo');
    }
}

class ModelRelationScopeTestStub implements Scope
{
    public function apply(Builder $query, Model $model)
    {
        $query->where('bar', '=', 'baz');
    }
}
