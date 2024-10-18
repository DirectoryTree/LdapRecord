<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use ReflectionFunction;

class BuilderScopeTest extends TestCase
{
    public function test_closure_scopes_can_be_applied()
    {
        $b = new Builder(new Connection);

        $b->withGlobalScope('foo', function ($query) use ($b) {
            $this->assertSame($b, $query);
        });

        $b->applyScopes();
    }

    public function test_class_scopes_can_be_applied()
    {
        $b = new Builder(new Connection);

        $b->setModel(new Entry);

        $b->withGlobalScope('foo', new TestModelScope);

        $this->assertEquals('(foo=LdapRecord\Models\Entry)', $b->getUnescapedQuery());

        $this->assertCount(1, $b->appliedScopes());
        $this->assertArrayHasKey('foo', $b->appliedScopes());
    }

    public function test_scopes_can_be_removed_after_being_added()
    {
        $b = new Builder(new Connection);

        $b->withGlobalScope('foo', function () {});

        $b->withoutGlobalScope('foo');

        $this->assertEquals(['foo'], $b->removedScopes());
    }

    public function test_many_scopes_can_be_removed_after_being_applied()
    {
        $b = new Builder(new Connection);

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

            return $func->getClosureThis()->filters['and'][0] == [
                'field' => 'foo',
                'operator' => '=',
                'value' => 'bar',
            ];
        }))->andReturn([]);

        $b = new Builder($connection);
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
