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

        $this->assertStringContainsString('(foo=bar)', $query->toBase()->getQuery());
    }

    public function test_scopes_are_applied_to_pagination_request()
    {
        $query = ModelWithGlobalScopeTestStub::query();

        $query->getConnection()
            ->getLdapConnection()
            ->shouldAllowAnyBind()
            ->expect(['search' => []]);

        $this->assertEmpty($query->paginate());
        $this->assertEmpty($query->paginate());
        $this->assertEmpty($query->paginate());

        $this->assertStringContainsString('(foo=bar)', $query->toBase()->getQuery());
    }

    public function test_scopes_are_not_stacked_multiple_times()
    {
        $query = ModelWithGlobalScopeTestStub::query();

        // Call toBase() multiple times to verify scopes aren't stacked
        $query->toBase();
        $query->toBase();

        $this->assertEquals('(&(foo=bar))', $query->toBase()->getQuery());
    }

    public function test_local_scopes_can_be_called()
    {
        $query = ModelWithLocalScopeTestStub::fooBar();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('(foo=\62\61\72)', $query->toBase()->getQuery());
    }

    public function test_local_scopes_accept_arguments()
    {
        $query = ModelWithLocalScopeTestStub::barBaz('zal');

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals('(bar=\7a\61\6c)', $query->toBase()->getQuery());
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

    public function test_scopes_cannot_be_negated_by_or_clauses()
    {
        // The scope should be wrapped in its own AND group, so the OR clause
        // cannot negate it. The filter should be: (&(bypass=...)(&(foo=bar)))
        // NOT: (|(foo=bar)(bypass=...)) which would allow bypassing the scope
        $this->assertEquals(
            '(&(bypass=\61\74\74\65\6d\70\74)(&(foo=bar)))',
            ModelWithGlobalScopeTestStub::query()
                ->orWhere('bypass', '=', 'attempt')
                ->toBase()
                ->getQuery()
        );
    }

    public function test_scopes_cannot_be_negated_by_multiple_or_clauses()
    {
        // Scope must remain enforced regardless of OR clauses
        $this->assertEquals(
            '(&(|(bypass1=\61\74\74\65\6d\70\74\31)(bypass2=\61\74\74\65\6d\70\74\32))(&(foo=bar)))',
            ModelWithGlobalScopeTestStub::query()
                ->orWhere('bypass1', '=', 'attempt1')
                ->orWhere('bypass2', '=', 'attempt2')
                ->toBase()
                ->getQuery()
        );
    }

    public function test_scopes_remain_enforced_with_complex_queries()
    {
        // The scope (foo=bar) must always be present and enforced at the root AND level
        $this->assertEquals(
            '(&(&(|(name=\4a\6f\68\6e)(name=\4a\61\6e\65))(active=\74\72\75\65))(&(foo=bar)))',
            ModelWithGlobalScopeTestStub::query()
                ->where('name', '=', 'John')
                ->orWhere('name', '=', 'Jane')
                ->where('active', '=', 'true')
                ->toBase()
                ->getQuery()
        );
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
