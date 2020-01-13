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
    public function test_scopes_can_be_added_to_models()
    {
        $model = new ModelScopeTestStub();
        $this->assertInstanceOf(\Closure::class, $model->getGlobalScopes()['foo']);
        $this->assertInstanceOf(ScopeTestStub::class, $model->getGlobalScopes()[ScopeTestStub::class]);
    }

    public function test_scopes_are_applied_to_query()
    {
        Container::addConnection(new Connection());

        $query = (new ModelScopeTestStub())->newQuery()->applyScopes();

        $this->assertEquals([
            'field'    => 'foo',
            'operator' => '=',
            'value'    => 'bar',
        ], $query->filters['and'][0]);
    }
}

class ModelScopeTestStub extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('foo', function () {
        });
        static::addGlobalScope(new ScopeTestStub());
    }
}

class ScopeTestStub implements Scope
{
    public function apply(Builder $query, Model $model)
    {
        $query->whereRaw('foo', '=', 'bar');
    }
}
