<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Model\Builder;

class ModelScopeTest extends TestCase
{
    protected function setUp() : void
    {
        ModelGlobalScopeTestStub::clearBootedModels();
    }

    public function test_scopes_can_be_added_to_models()
    {
        $model = new ModelGlobalScopeTestStub();
        $this->assertInstanceOf(\Closure::class, $model->getGlobalScopes()['foo']);
        $this->assertInstanceOf(ScopeTestStub::class, $model->getGlobalScopes()[ScopeTestStub::class]);
    }

    public function test_has_scope()
    {
        $this->assertFalse(ModelGlobalScopeTestStub::hasGlobalScope('foo'));
        $this->assertFalse(ModelGlobalScopeTestStub::hasGlobalScope(ScopeTestStub::class));

        new ModelGlobalScopeTestStub();
        $this->assertTrue(ModelGlobalScopeTestStub::hasGlobalScope('foo'));
        $this->assertTrue(ModelGlobalScopeTestStub::hasGlobalScope(ScopeTestStub::class));

        $this->assertCount(2, (new ModelGlobalScopeTestStub())->getGlobalScopes());
    }

    public function test_scopes_are_applied_to_query()
    {
        Container::addConnection(new Connection());

        $query = (new ModelGlobalScopeTestStub())->newQuery()->applyScopes();

        $this->assertEquals([
            'field'    => 'foo',
            'operator' => '=',
            'value'    => 'bar',
        ], $query->filters['and'][0]);
    }

    public function test_scopes_are_applied_to_pagination_request()
    {
        Container::addConnection(new Connection());

        $query = (new ModelGlobalScopeTestStub())->newQuery();
        $this->assertEmpty($query->paginate());

        $this->assertEquals([
            'field'    => 'foo',
            'operator' => '=',
            'value'    => 'bar',
        ], $query->filters['and'][0]);
    }

    public function test_scopes_are_not_stacked_multiple_times()
    {
        Container::addConnection(new Connection());

        $query = (new ModelGlobalScopeTestStub())->newQuery();
        $query->getQuery();
        $query->getQuery();

        $this->assertCount(1, $query->filters['and']);
        $this->assertEquals('(foo=bar)', $query->getQuery());
    }

    public function test_local_scopes_can_be_called()
    {
        Container::addConnection(new Connection());

        $query = ModelLocalScopeTestStub::fooBar();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->filters['and']);
        $this->assertEquals('foo', $query->filters['and'][0]['field']);
        $this->assertEquals('=', $query->filters['and'][0]['operator']);
        $this->assertEquals('\62\61\72', $query->filters['and'][0]['value']->get());
    }
}

class ModelLocalScopeTestStub extends Model
{
    public function scopeFooBar($query)
    {
        return $query->where('foo', '=', 'bar');
    }
}

class ModelGlobalScopeTestStub extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('foo', function () {
        });
        static::addGlobalScope(new ScopeTestStub());
    }

    public function newQueryBuilder(Connection $connection)
    {
        return new ModelBuilderTestStub($connection);
    }
}

class ScopeTestStub implements Scope
{
    public function apply(Builder $query, Model $model)
    {
        $query->whereRaw('foo', '=', 'bar');
    }
}

class ModelBuilderTestStub extends Builder
{
    protected function runPaginate($filter, $perPage, $isCritical)
    {
        return [];
    }
}
