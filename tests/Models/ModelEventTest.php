<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;
use LdapRecord\Models\Events\Saved;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Events\Saving;
use LdapRecord\Models\Events\Created;
use LdapRecord\Models\Events\Deleted;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Creating;
use LdapRecord\Models\Events\Deleting;
use LdapRecord\Models\Events\Updating;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\Tests\CreatesConnectedLdapMocks;

class ModelEventTest extends TestCase
{
    use CreatesConnectedLdapMocks;

    public function test_save_fires_saving_and_create_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class)->makePartial();
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Creating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Created::class);
        Container::setEventDispatcher($dispatcher);

        $model = new ModelEventSaveStub();
        $this->assertTrue($model->save());
    }

    public function test_create_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Creating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Created::class);
        Container::setEventDispatcher($dispatcher);

        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('add')->once()->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

        $model = m::mock(Entry::class)->makePartial();
        $model->shouldReceive('newQuery')->once()->andReturn($query);
        $model->shouldReceive('synchronize')->once()->andReturnTrue();

        $model->setDn('cn=foo,dc=bar,dc=baz');
        $model->cn = 'foo';
        $model->objectclass = 'bar';
        $this->assertTrue($model->save());
    }

    public function test_updating_model_fires_events()
    {
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class);
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class);
        $dispatcher->shouldReceive('fire')->once()->with(Updated::class);
        Container::setEventDispatcher($dispatcher);

        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('modifyBatch')->once()->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

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
        $dispatcher->shouldReceive('fire')->once()->with(Deleting::class);
        $dispatcher->shouldReceive('fire')->once()->with(Deleted::class);
        Container::setEventDispatcher($dispatcher);

        $ldap = $this->newConnectedLdapMock();
        $ldap->shouldReceive('delete')->once()->andReturnTrue();

        $query = new Builder(new Connection([], $ldap));

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
    public function newQueryWithoutScopes()
    {
        return (new ModelQueryBuilderSaveStub(new Connection()))->setModel($this);
    }

    public function synchronize()
    {
        return true;
    }
}

class ModelQueryBuilderSaveStub extends Builder
{
    public function insert($dn, array $attributes)
    {
        return true;
    }

    public function update($dn, array $modifications)
    {
        return true;
    }
}
