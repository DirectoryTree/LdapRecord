<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Builder as QueryBuilder;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use ReflectionFunction;

class BuilderScopeTest extends TestCase
{
    public function test_closure_scopes_can_be_applied()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $scoped = null;

        $b->withGlobalScope('foo', function ($query) use (&$scoped) {
            $scoped = $query;
        });

        $applied = $b->applyScopes();

        $this->assertNotSame($b, $applied);
        $this->assertSame($applied, $scoped);
    }

    public function test_class_scopes_can_be_applied()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $b->setModel(new Entry);

        $b->withGlobalScope('foo', new TestModelScope);

        // Scope filters are composed onto the scoped query builder.
        $scoped = $b->applyScopes();

        $this->assertEquals('(foo=LdapRecord\Models\Entry)', $scoped->getUnescapedQuery());

        $this->assertEmpty($b->appliedScopes());
        $this->assertCount(1, $scoped->appliedScopes());
        $this->assertArrayHasKey('foo', $scoped->appliedScopes());
    }

    public function test_scope_or_filters_are_preserved_when_grouped()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $b->where('cn', '=', 'John Doe');

        $b->withGlobalScope('foo', function ($query) {
            $query->orWhere('foo', '=', 'bar');
            $query->orWhere('bar', '=', 'baz');
        });

        $this->assertEquals(
            '(&(cn=John Doe)(|(foo=bar)(bar=baz)))',
            $b->toBase()->getUnescapedQuery()
        );
    }

    public function test_complex_scope_filters_cannot_be_negated_by_complex_queries()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $b->where('department', '=', 'Sales')
            ->orWhere('department', '=', 'Support')
            ->where('enabled', '=', 'true');

        $b->withGlobalScope('foo', function ($query) {
            $query->orFilter(function ($query) {
                $query->where('type', '=', 'person');
                $query->where('type', '=', 'contact');
            });

            $query->where('tenant', '=', 'acme');
        });

        $this->assertEquals(
            '(&(|(department=Sales)(department=Support))(enabled=true)(&(|(type=person)(type=contact))(tenant=acme)))',
            $b->toBase()->getUnescapedQuery()
        );
    }

    public function test_scopes_can_be_removed_after_being_added()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $b->withGlobalScope('foo', function () {});

        $b->withoutGlobalScope('foo');

        $this->assertEquals(['foo'], $b->removedScopes());
    }

    public function test_many_scopes_can_be_removed_after_being_applied()
    {
        $b = new Builder(new Entry, new QueryBuilder(new Connection));

        $b->withGlobalScope('foo', function () {});
        $b->withGlobalScope('bar', function () {});

        $b->withoutGlobalScopes(['foo', 'bar']);

        $this->assertEquals(['foo', 'bar'], $b->removedScopes());
    }

    public function test_scopes_are_applied_when_getting_records()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('run')->once()->with(m::on(function ($closure) {
            $func = new ReflectionFunction($closure);

            // Check that the filter contains the expected filter
            $filter = $func->getClosureThis()->getFilter();

            return $filter && str_contains((string) $filter, '(foo=bar)');
        }))->andReturn([]);

        $b = new Builder(new Entry, new QueryBuilder($connection));
        $b->setModel(new Entry);

        $b->withGlobalScope('foo', function ($query) {
            $query->whereRaw('foo', '=', 'bar');
        });

        $b->get();
    }
}

class TestModelScope implements Scope
{
    public function apply(Builder $query, Model $model): void
    {
        $query->where('foo', '=', get_class($model));
    }
}
