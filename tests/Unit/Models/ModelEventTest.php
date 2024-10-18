<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Events\Created;
use LdapRecord\Models\Events\Creating;
use LdapRecord\Models\Events\Deleted;
use LdapRecord\Models\Events\Deleting;
use LdapRecord\Models\Events\Saved;
use LdapRecord\Models\Events\Saving;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Updating;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelEventTest extends TestCase
{
    public function test_save_fires_saving_and_create_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class)->makePartial();

        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Creating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Created::class);

        Container::getInstance()->setDispatcher($dispatcher);

        (new ModelEventSaveStub)->save();
    }

    public function test_save_quietly_does_not_fire_any_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class)->makePartial();

        $dispatcher->shouldNotReceive('fire');

        Container::getInstance()->setDispatcher($dispatcher);

        (new ModelEventSaveStub)->saveQuietly();

        $this->assertEquals($dispatcher, Container::getInstance()->getDispatcher());
    }

    public function test_create_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Creating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Created::class);

        Container::getInstance()->setDispatcher($dispatcher);

        $expectation = LdapFake::operation('add')
            ->once()
            ->with(
                'cn=foo,dc=bar,dc=baz',
                [
                    'cn' => ['foo'],
                    'objectclass' => ['bar'],
                ]
            )
            ->andReturnTrue();

        $ldap = (new LdapFake)->expect(['isBound' => true, $expectation]);

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('getConnectionContainer')->andReturn(Container::getInstance());
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setDn('cn=foo,dc=bar,dc=baz')->fill([
            'cn' => 'foo',
            'objectclass' => 'bar',
        ])->save();
    }

    public function test_updating_model_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Updated::class);

        Container::getInstance()->setDispatcher($dispatcher);

        $modifyBatchExpectation = LdapFake::operation('modifyBatch')
            ->once()
            ->with([
                'cn=foo,dc=bar,dc=baz',
                [
                    [
                        'attrib' => 'cn',
                        'modtype' => 1,
                        'values' => ['foo'],
                    ],
                ],
            ])->andReturnTrue();

        $ldap = (new LdapFake)->expect(['isBound' => true, $modifyBatchExpectation]);

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('getConnectionContainer')->andReturn(Container::getInstance());
        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'cn=foo,dc=bar,dc=baz']);
        $model->cn = 'foo';
        $model->update();
    }

    public function test_deleting_model_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);

        $dispatcher->shouldReceive('fire')->once()->with(Deleting::class);
        $dispatcher->shouldReceive('fire')->once()->with(Deleted::class);

        Container::getInstance()->setDispatcher($dispatcher);

        $expectation = LdapFake::operation('delete')->once()->with('cn=foo,dc=bar,dc=baz')->andReturnTrue();

        $ldap = (new LdapFake)->expect(['isBound' => true, $expectation]);

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();

        $model->shouldReceive('newQuery')->once()->andReturn($query);

        $model->setRawAttributes(['dn' => 'cn=foo,dc=bar,dc=baz']);

        $model->cn = 'foo';

        $model->delete();
    }
}

class ModelQueryBuilderSaveStub extends Builder
{
    public function insertAndGetDn(string $dn, array $attributes): string|false
    {
        return $dn;
    }

    public function update(string $dn, array $modifications): bool
    {
        return true;
    }
}

class ModelEventSaveStub extends Model
{
    public function newQueryWithoutScopes(): ModelQueryBuilderSaveStub
    {
        return (new ModelQueryBuilderSaveStub(new Connection))->setModel($this);
    }

    public function refresh(): bool
    {
        return true;
    }
}
