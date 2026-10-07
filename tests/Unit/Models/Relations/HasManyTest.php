<?php

namespace LdapRecord\Tests\Unit\Models\Relations;

use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class HasManyTest extends TestCase
{
    public function test_detach_or_delete_parent_with_multiple_results()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('is')->once()->andReturnTrue();
        $model->shouldReceive('delete')->never();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection([$model, $model]));

        $relation->shouldReceive('detach')->with($model)->once();

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_one_result()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('is')->once()->andReturnTrue();
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->once();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection([$model]));
        $relation->shouldReceive('getParent')->once()->andReturn($related);

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_single_non_matching_result()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $related = m::mock(Entry::class);
        $related->shouldReceive('delete')->never();

        $other = m::mock(Entry::class);
        $other->shouldReceive('is')->once()->with($model)->andReturnFalse();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(
            new Collection([$other])
        );

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_or_delete_parent_with_no_results_does_not_delete_parent()
    {
        $model = m::mock(Entry::class);
        $model->shouldReceive('delete')->never();

        $relation = m::mock(HasMany::class)->makePartial();
        $relation->shouldReceive('get')->with('dn')->andReturn(new Collection);

        $relation->detachOrDeleteParent($model);
    }

    public function test_detach_all_or_delete_preserves_groups_with_other_members()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $user = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $group = (new Group)->setRawAttributes([
            'dn' => 'cn=Staff,dc=local',
            'member' => [$user->getDn(), 'cn=Bob,dc=local'],
            'memberof' => ['cn=Parent One,dc=local', 'cn=Parent Two,dc=local'],
        ]);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => [$user->getDn(), 'cn=Bob,dc=local']],
            ]),
            LdapFake::operation('search')->andReturn([
                ['dn' => 'cn=Parent One,dc=local', 'objectclass' => Group::$objectClasses],
                ['dn' => 'cn=Parent Two,dc=local', 'objectclass' => Group::$objectClasses],
            ]),
            LdapFake::operation('modDelete')->once()->with($group->getDn(), ['member' => [$user->getDn()]])->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$user->newQuery(), $user, Group::class, 'member', 'dn', 'groups'])->makePartial();
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$group]));

        $this->assertSame($group, $relation->detachAllOrDelete()->first());
        $this->assertTrue($group->exists);
        $this->assertSame(['cn=Bob,dc=local'], $group->member);

        $connection->tearDown();
    }

    public function test_detach_all_or_delete_deletes_groups_when_detaching_their_last_member()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $user = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $group = (new Group)->setRawAttributes([
            'dn' => 'cn=Staff,dc=local',
            'member' => [$user->getDn()],
        ]);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => ['CN=Alice,DC=local']],
            ]),
            LdapFake::operation('delete')->once()->with($group->getDn())->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$user->newQuery(), $user, Group::class, 'member', 'dn', 'groups'])->makePartial();
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$group]));

        $relation->detachAllOrDelete();

        $this->assertFalse($group->exists);

        $connection->tearDown();
    }

    public function test_detach_all_or_delete_reads_membership_when_the_related_model_is_partially_loaded()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $user = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $group = (new Group)->setRawAttributes(['dn' => 'cn=Staff,dc=local']);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => [$user->getDn(), 'cn=Bob,dc=local']],
            ]),
            LdapFake::operation('modDelete')->once()->with($group->getDn(), ['member' => [$user->getDn()]])->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$user->newQuery(), $user, Group::class, 'member', 'dn', 'groups'])->makePartial();
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$group]));

        $relation->detachAllOrDelete();

        $this->assertTrue($group->exists);

        $connection->tearDown();
    }

    public function test_detach_all_or_delete_uses_the_configured_membership_model_and_key()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $group = (new Group)->setRawAttributes(['dn' => 'cn=Staff,dc=local']);
        $alice = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $bob = (new User)->setRawAttributes(['dn' => 'cn=Bob,dc=local']);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => [$alice->getDn(), $bob->getDn()]],
            ]),
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => [$bob->getDn()]],
            ]),
            LdapFake::operation('modDelete')->once()->with($group->getDn(), ['member' => [$alice->getDn()]])->andReturnTrue(),
            LdapFake::operation('delete')->once()->with($group->getDn())->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$group->newQuery(), $group, User::class, 'memberof', 'dn', 'members'])->makePartial();
        $relation->using($group, 'member');
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$alice, $bob]));

        $relation->detachAllOrDelete();

        $this->assertFalse($group->exists);
        $this->assertTrue($alice->exists);
        $this->assertTrue($bob->exists);

        $connection->tearDown();
    }

    public function test_detach_all_or_delete_stops_when_the_membership_model_is_deleted()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $group = (new Group)->setRawAttributes(['dn' => 'cn=Staff,dc=local']);
        $alice = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $nestedMember = (new User)->setRawAttributes(['dn' => 'cn=Bob,dc=local']);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => [$alice->getDn()]],
            ]),
            LdapFake::operation('delete')->once()->with($group->getDn())->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$group->newQuery(), $group, User::class, 'memberof', 'dn', 'members'])->makePartial();
        $relation->using($group, 'member');
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$alice, $nestedMember]));

        $relation->detachAllOrDelete();

        $this->assertFalse($group->exists);
        $this->assertTrue($alice->exists);
        $this->assertTrue($nestedMember->exists);

        $connection->tearDown();
    }

    public function test_detach_all_or_delete_preserves_a_group_whose_last_member_does_not_match()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();
        Container::addConnection($connection);

        $user = (new User)->setRawAttributes(['dn' => 'cn=Alice,dc=local']);
        $group = (new Group)->setRawAttributes(['dn' => 'cn=Staff,dc=local']);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with($group->getDn(), '(objectclass=*)', ['objectguid', 'member', 'objectclass'])->andReturn([
                ['dn' => $group->getDn(), 'member' => ['cn=Bob,dc=local']],
            ]),
            LdapFake::operation('modDelete')->once()->with($group->getDn(), ['member' => [$user->getDn()]])->andReturnTrue(),
        ]);

        $relation = m::mock(HasMany::class.'[get]', [$user->newQuery(), $user, Group::class, 'member', 'dn', 'groups'])->makePartial();
        $relation->shouldReceive('get')->once()->andReturn(new Collection([$group]));

        $relation->detachAllOrDelete();

        $this->assertTrue($group->exists);

        $connection->tearDown();
    }
}
