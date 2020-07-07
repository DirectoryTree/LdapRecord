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
use LdapRecord\Models\Attributes\EscapedValue;

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

    public function test_has_default_model()
    {
        $this->assertEquals(
            Entry::class,
            (new ModelRelationTestStub())->relation()->getDefaultModel()
        );

        $this->assertInstanceOf(
            Entry::class,
            (new ModelRelationTestStub())->relation()->getNewDefaultModel()
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

    public function test_exists()
    {
        $relation = (new ModelRelationTestStub())->relation();

        $this->assertFalse($relation->exists());

        $related = new Entry();
        $related->setDn('cn=foo,dc=local,dc=com');

        $unrelated = new Entry();
        $unrelated->setDn('cn=bar,dc=local,dc=com');

        $relation->setResults([$related]);
        $this->assertTrue($relation->exists(null));
        $this->assertTrue($relation->exists($related));
        $this->assertTrue($relation->exists([$related]));
        $this->assertTrue($relation->exists('foo'));
        $this->assertTrue($relation->exists('cn=foo,dc=local,dc=com'));


        $this->assertFalse($relation->exists([$related, $unrelated]));
        $this->assertFalse($relation->exists(['cn=foo,dc=local,dc=com', 'cn=bar,dc=local,dc=com']));
        $this->assertFalse($relation->exists('bar'));
    }

    public function test_contains()
    {
        $relation = (new ModelRelationTestStub())->relation();

        $this->assertFalse($relation->contains('foo'));

        $related = new Entry();
        $related->setDn('cn=foo,dc=local,dc=com');

        $unrelated = new Entry();
        $unrelated->setDn('cn=bar,dc=local,dc=com');

        $relation->setResults([$related]);

        $this->assertTrue($relation->contains('foo'));
        $this->assertTrue($relation->contains('cn=foo,dc=local,dc=com'));
        $this->assertTrue($relation->contains($related));
        $this->assertTrue($relation->contains(['foo', 'bar']));
        $this->assertTrue($relation->contains([$related, 'bar']));

        $this->assertFalse($relation->contains(null));
        $this->assertFalse($relation->contains(['']));
        $this->assertFalse($relation->contains($unrelated));
        $this->assertFalse($relation->contains(['bar', 'baz']));
    }

    public function test_relation_default_model_uses_parent_connection()
    {
        Container::addConnection(new Connection(), 'other');

        $model = new ModelRelationTestStub();

        $model->setConnection($connection = 'other');

        $defaultModel = $model->relation()->getNewDefaultModel();

        $this->assertEquals($connection, $defaultModel->getConnectionName());
    }

    public function test_relation_query_uses_models_parent_connection()
    {
        Container::addConnection($connection = new Connection(), 'other');

        $model = new ModelRelationTestStub();

        $model->setConnection($connectionName = 'other');

        $relationQuery = $model->relation()->getQuery();

        $defaultModel = $relationQuery->getModel();

        $this->assertEquals($connection, $relationQuery->getConnection());
        $this->assertEquals($connectionName, $defaultModel->getConnectionName());
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

    public function test_has_many_foreign_values_are_properly_escaped_for_use_in_filters()
    {
        $escapedDnCharacters = ['\\', ',', '=', '+', '<', '>', ';', '"', '#'];
        $escapedFilterCharacters = ['\\', '*', '(', ')', "\x00"];

        $model = new ModelWithHasManyRelationTestStub();

        $characters = implode('', array_merge($escapedDnCharacters, $escapedFilterCharacters));

        $model->setDn($characters);

        $expected = (new EscapedValue($characters))->both()->get();

        $this->assertEquals(
            "(foo=$expected)",
            $model->relation()->getRelationQuery()->getQuery()
        );
    }
}

class RelationTestStub extends Relation
{
    protected $results = [];

    public function getResults()
    {
        return $this->parent->newCollection($this->results);
    }

    public function setResults($results)
    {
        $this->results = $results;

        return $this;
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

class ModelWithHasManyRelationTestStub extends Model
{
    public function relation()
    {
        return $this->hasMany(static::class, 'foo');
    }
}
