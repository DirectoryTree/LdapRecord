<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Tests\TestCase;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Testing\DirectoryFake;

class ModelScopeTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        Container::addConnection(new Connection());

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
        $query = (new ModelGlobalScopeTestStub())->newQuery()->applyScopes();

        $this->assertEquals([
            'field'    => 'foo',
            'operator' => '=',
            'value'    => 'bar',
        ], $query->filters['and'][0]);
    }

    public function test_scopes_are_applied_to_pagination_request()
    {
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
        $query = (new ModelGlobalScopeTestStub())->newQuery();
        $query->getQuery();
        $query->getQuery();

        $this->assertCount(1, $query->filters['and']);
        $this->assertEquals('(foo=bar)', $query->getQuery());
    }

    public function test_local_scopes_can_be_called()
    {
        $query = ModelLocalScopeTestStub::fooBar();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->filters['and']);
        $this->assertEquals('foo', $query->filters['and'][0]['field']);
        $this->assertEquals('=', $query->filters['and'][0]['operator']);
        $this->assertEquals('\62\61\72', $query->filters['and'][0]['value']->get());
    }

    public function test_local_scopes_accept_arguments()
    {
        $query = ModelLocalScopeTestStub::barBaz('zal');

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->filters['and']);
        $this->assertEquals('bar', $query->filters['and'][0]['field']);
        $this->assertEquals('=', $query->filters['and'][0]['operator']);
        $this->assertEquals('\7a\61\6c', $query->filters['and'][0]['value']->get());
    }

    public function test_scopes_do_not_impact_model_refresh()
    {
        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('read')->once()->with('cn=John Doe,dc=local,dc=com')->andReturn([
                ['dn' => 'cn=John Doe,dc=local,dc=com']
            ])
        );

        $model = (new ModelScopeWithDnScopeTestStub())
            ->setRawAttributes(['dn' => 'cn=John Doe,dc=local,dc=com']);

        $model->fresh();

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $model->getDn());
    }

    public function test_scopes_do_not_impact_model_find()
    {
        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('read')->once()->with('cn=John Doe,dc=local,dc=com')->andReturn([
                ['dn' => 'cn=John Doe,dc=local,dc=com']
            ])
        );

        $model = ModelScopeWithDnScopeTestStub::find('cn=John Doe,dc=local,dc=com');

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $model->getDn());
    }
}

class ModelLocalScopeTestStub extends Model
{
    public function scopeFooBar($query)
    {
        return $query->where('foo', '=', 'bar');
    }

    public function scopeBarBaz($query, $parameter)
    {
        return $query->where('bar', '=', $parameter);
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

class ModelScopeWithDnScopeTestStub extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($query) {
            $query->in('global-scope');
        });
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
