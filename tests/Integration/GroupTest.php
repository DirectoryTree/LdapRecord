<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Tests\Integration\Fixtures\Group;

class GroupTest extends TestCase
{
    protected OrganizationalUnit $ou;

    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection($this->makeConnection());

        $this->ou = OrganizationalUnit::query()->where('ou', 'Group Test OU')->firstOr(function () {
            return OrganizationalUnit::create(['ou' => 'Group Test OU']);
        });

        $this->ou->deleteLeafNodes();
    }

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::flush();

        parent::tearDown();
    }

    public function test_it_can_be_created()
    {
        $group = $this->createGroup($this->ou);

        $this->assertTrue($group->exists);
        $this->assertTrue($group->wasRecentlyCreated);

        $this->assertCount(1, Group::all());
    }

    public function test_it_can_attach_members()
    {
        $group = $this->createGroup($this->ou, ['cn' => 'Foo']);
        $userOne = $this->createUser($this->ou, ['cn' => 'Bar']);
        $userTwo = $this->createUser($this->ou, ['cn' => 'Baz']);

        $group->users()->attach($userOne);
        $group->users()->attach($userTwo);

        $this->assertCount(2, $users = $group->users()->get());

        $this->assertEquals([
            $userOne->getFirstAttribute('uid'),
            $userTwo->getFirstAttribute('uid'),
        ], $users->pluck('uid.0')->toArray());
    }

    public function test_it_can_detach_members()
    {
        $group = $this->createGroup($this->ou, ['cn' => 'Foo']);
        $user = $this->createUser($this->ou, ['cn' => 'Bar']);

        $group->users()->attach($user);

        $this->assertCount(1, $group->users()->get());

        $group->users()->detach($user);

        $this->assertCount(0, $group->users()->get());
    }

    public function test_it_can_detach_or_delete()
    {
        $group = $this->createGroup($this->ou, ['cn' => 'Foo']);
        $user = $this->createUser($this->ou, ['cn' => 'Bar']);

        $group->users()->attach($user);

        $this->assertTrue($group->exists);

        $group->users()->detachOrDeleteParent($user);

        $this->assertFalse($group->exists);
    }

    public function test_it_can_detach_or_delete_with_multiple_users()
    {
        $group = $this->createGroup($this->ou, ['cn' => 'Foo']);
        $userOne = $this->createUser($this->ou, ['cn' => 'Bar']);
        $userTwo = $this->createUser($this->ou, ['cn' => 'Baz']);

        $group->users()->attachMany([$userOne, $userTwo]);

        $group->users()->detachOrDeleteParent($userOne);

        $this->assertTrue($group->exists);
    }
}
