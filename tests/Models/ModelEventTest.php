<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Models\Events\Saved;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Events\Saving;
use LdapRecord\Container;
use LdapRecord\Models\Events\Created;
use LdapRecord\Models\Events\Deleted;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Creating;
use LdapRecord\Models\Events\Deleting;
use LdapRecord\Models\Events\Updating;
use LdapRecord\LdapInterface;
use LdapRecord\Events\DispatcherInterface;

class ModelEventTest extends TestCase
{
    protected function setUp()
    {
        // Flush event dispatcher instance.
        Container::unsetEventDispatcher();
    }

    public function test_save_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class)->makePartial();
        $dispatcher->shouldReceive('fire')->once()->withArgs([Saving::class]);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Saved::class]);
        Container::setEventDispatcher($dispatcher);

        $model = new ModelEventSaveStub();
        $this->assertTrue($model->save());
    }

    public function test_create_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Creating::class]);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Created::class]);
        Container::setEventDispatcher($dispatcher);

        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('add')->once()->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setDn('cn=foo,dc=bar,dc=baz');
        $model->cn = 'foo';
        $model->objectclass = 'bar';
        $this->assertTrue($model->create());
    }

    public function test_updating_model_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Updating::class]);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Updated::class]);
        Container::setEventDispatcher($dispatcher);

        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('modifyBatch')->once()->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setRawAttributes(['dn' => 'cn=foo,dc=bar,dc=baz']);
        $model->cn = 'foo';
        $this->assertTrue($model->update());
    }

    public function test_deleting_model_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Deleting::class]);
        $dispatcher->shouldReceive('fire')->once()->withArgs([Deleted::class]);
        Container::setEventDispatcher($dispatcher);

        $conn = m::mock(LdapInterface::class);
        $conn->shouldReceive('delete')->once()->andReturnTrue();

        $query = new Builder($conn);

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldNotReceive('synchronize');

        $model->setRawAttributes(['dn' => 'cn=foo,dc=bar,dc=baz']);
        $model->cn = 'foo';
        $this->assertTrue($model->delete());
    }
}

class ModelEventSaveStub extends Model
{
    public function create(array $attributes = [])
    {
        return true;
    }
}
