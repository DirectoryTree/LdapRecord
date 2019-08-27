<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Connections\Container;
use LdapRecord\Connections\Connection;
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
