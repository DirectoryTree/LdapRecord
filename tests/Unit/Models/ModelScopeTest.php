<?php

namespace LdapRecord\Tests\Unit\Models;

use Closure;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ModelScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);

        DirectoryFake::setup();

        ModelWithGlobalScopeTestStub::clearBootedModels();
    }

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();

        parent::tearDown();
    }

    public function test_scopes_can_be_added_to_models()
    {
        $model = new ModelWithGlobalScopeTestStub;

        $this->assertInstanceOf(Closure::class, $model->getGlobalScopes()['foo']);
        $this->assertInstanceOf(ScopeTestStub::class, $model->getGlobalScopes()[ScopeTestStub::class]);
    }

    public function test_has_scope()
    {
        $this->assertFalse(ModelWithGlobalScopeTestStub::hasGlobalScope('foo'));
        $this->assertFalse(ModelWithGlobalScopeTestStub::hasGlobalScope(ScopeTestStub::class));

        new ModelWithGlobalScopeTestStub;

        $this->assertTrue(ModelWithGlobalScopeTestStub::hasGlobalScope('foo'));
        $this->assertTrue(ModelWithGlobalScopeTestStub::hasGlobalScope(ScopeTestStub::class));
        $this->assertCount(2, (new ModelWithGlobalScopeTestStub)->getGlobalScopes());
    }

    public function test_scopes_are_applied_to_query()
    {
        $query = ModelWithGlobalScopeTestStub::query();

        $this->assertEquals([
            'attribute' => 'foo',
            'operator' => '=',
            'value' => 'bar',
        ], $query->toBase()->filters['and'][0]);
    }

    public function test_scopes_are_applied_to_pagination_request()
    {
        $query = ModelWithGlobalScopeTestStub::query();

        $query->getConnection()
            ->getLdapConnection()
            ->shouldAllowAnyBind()
            ->expect(['search' => []]);

        $this->assertEmpty($query->paginate());

        $this->assertEquals([
            'attribute' => 'foo',
            'operator' => '=',
            'value' => 'bar',
        ], $query->toBase()->filters['and'][0]);
    }

    public function test_scopes_are_not_stacked_multiple_times()
    {
        $query = ModelWithGlobalScopeTestStub::query();
        $query->getQuery();
        $query->getQuery();

        $this->assertCount(1, $query->toBase()->filters['and']);
        $this->assertEquals('(foo=bar)', $query->getQuery()->getQuery());
    }

    public function test_local_scopes_can_be_called()
    {
        $query = ModelWithLocalScopeTestStub::fooBar();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->toBase()->filters['and']);
        $this->assertEquals('foo', $query->toBase()->filters['and'][0]['attribute']);
        $this->assertEquals('=', $query->toBase()->filters['and'][0]['operator']);
        $this->assertEquals('\62\61\72', $query->toBase()->filters['and'][0]['value']);
    }

    public function test_local_scopes_accept_arguments()
    {
        $query = ModelWithLocalScopeTestStub::barBaz('zal');

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertCount(1, $query->toBase()->filters['and']);
        $this->assertEquals('bar', $query->toBase()->filters['and'][0]['attribute']);
        $this->assertEquals('=', $query->toBase()->filters['and'][0]['operator']);
        $this->assertEquals('\7a\61\6c', $query->toBase()->filters['and'][0]['value']);
    }

    public function test_scopes_do_not_impact_model_refresh()
    {
        DirectoryFake::setup()->getLdapConnection()->expect(
            [
                LdapFake::operation('read')->once()->with('cn=John Doe,dc=local,dc=com')->andReturn([
                    ['dn' => 'cn=John Doe,dc=local,dc=com'],
                ]),
            ]
        );

        $model = (new ModelWithDnScopeTestStub)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=local,dc=com',
        ]);

        $model->fresh();

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $model->getDn());
    }

    public function test_scopes_do_not_impact_model_find()
    {
        DirectoryFake::setup()->getLdapConnection()->expect(
            [
                LdapFake::operation('read')->once()->with('cn=John Doe,dc=local,dc=com')->andReturn([
                    ['dn' => 'cn=John Doe,dc=local,dc=com'],
                ]),
            ]
        );

        $model = ModelWithDnScopeTestStub::find('cn=John Doe,dc=local,dc=com');

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $model->getDn());
    }
}

class ModelWithLocalScopeTestStub extends Model
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

class ModelWithGlobalScopeTestStub extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('foo', function () {});
        static::addGlobalScope(new ScopeTestStub);
    }

    public function newQueryBuilder(Connection $connection): Builder
    {
        return new ModelBuilderTestStub($this, $connection->query());
    }
}

class ModelWithDnScopeTestStub extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope(function ($query) {
            $query->in('global-scope');
        });
    }
}

class ScopeTestStub implements Scope
{
    public function apply(Builder $query, Model $model): void
    {
        $query->whereRaw('foo', '=', 'bar');
    }
}

class ModelBuilderTestStub extends Builder
{
    protected function runPaginate(string $filter, int $perPage, bool $isCritical): array
    {
        return [];
    }
}
