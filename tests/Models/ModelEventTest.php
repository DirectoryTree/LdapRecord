<?php

namespace LdapRecord\Tests\Models;

use Mockery as m;
use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Connections\Container;
use LdapRecord\Models\Events\Created;
use LdapRecord\Models\Events\Creating;
use LdapRecord\Models\Events\Deleted;
use LdapRecord\Models\Events\Deleting;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Updating;
use LdapRecord\Connections\LdapInterface;
use LdapRecord\Events\DispatcherInterface;

class ModelEventTest extends TestCase
{
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
        $model->fill(['cn' => 'foo', 'objectclass' => 'bar']);
        $this->assertTrue($model->create());
    }

    public function test_updating_model_fires_events()
    {
        $c = $this->newLdapMock();

        $m = $this->newModel([], $this->newBuilder($c));

        $m->setRawAttributes([
            'dn' => 'cn=jdoe,dc=acme,dc=org'
        ]);

        $d = Manager::getEventDispatcher();

        $firedUpdating = false;
        $firedUpdated = false;

        $d->listen(Updating::class, function (Updating $e) use (&$firedUpdating) {
            $this->assertInstanceOf(Model::class, $e->getModel());

            $firedUpdating = true;
        });

        $d->listen(Updated::class, function (Updated $e) use (&$firedUpdated) {
            $this->assertInstanceOf(Model::class, $e->getModel());

            $firedUpdated = true;
        });

        $c
            ->shouldReceive('modifyBatch')->once()->andReturn(true)
            ->shouldReceive('read')->once()
            ->shouldReceive('getEntries')->once();

        $m->save([
            'cn' => 'new'
        ]);

        $this->assertTrue($firedUpdating);
        $this->assertTrue($firedUpdated);
    }

    public function test_deleting_model_fires_events()
    {
        $c = $this->newLdapMock();

        $m = $this->newModel([], $this->newBuilder($c));

        $m->setRawAttributes([
            'dn' => 'cn=jdoe,dc=acme,dc=org'
        ]);

        $d = Manager::getEventDispatcher();

        $firedDeleting = false;
        $firedDeleted = false;

        $d->listen(Deleting::class, function (Deleting $e) use (&$firedDeleting) {
            $this->assertInstanceOf(Model::class, $e->getModel());

            $firedDeleting = true;
        });

        $d->listen(Deleted::class, function (Deleted $e) use (&$firedDeleted) {
            $this->assertInstanceOf(Model::class, $e->getModel());

            $firedDeleted = true;
        });

        $c->shouldReceive('delete')->once()->andReturn(true);

        $m->delete();

        $this->assertTrue($firedDeleting);
        $this->assertTrue($firedDeleted);
    }

    public function test_model_events_can_be_listened_for_with_wildcard()
    {
        $c = $this->newLdapMock();

        $m = $this->newModel([], $this->newBuilder($c));

        $m->setRawAttributes([
            'dn' => 'cn=jdoe,dc=acme,dc=org'
        ]);

        $d = Manager::getEventDispatcher();

        $firedDeleting = false;
        $firedDeleted = false;

        $d->listen('LdapRecord\Models\Events\*', function ($event, $payload) use (&$firedDeleting, &$firedDeleted) {
            if ($event == 'LdapRecord\Models\Events\Deleting') {
                $firedDeleting = true;
            } else if ($event == 'LdapRecord\Models\Events\Deleted') {
                $firedDeleted = true;
            }
        });

        $c->shouldReceive('delete')->once()->andReturn(true);

        $m->delete();

        $this->assertTrue($firedDeleting);
        $this->assertTrue($firedDeleted);
    }
}