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

    protected function createGroup(array $attributes = []): Group
    {
        $group = (new Group())
            ->inside($this->ou)
            ->fill(array_merge([
                'cn'        => 'Foo',
                'gidNumber' => 500,
            ], $attributes));

        $group->save();

        return $group;
    }

    public function test_it_can_be_created()
    {
        $group = $this->createGroup();

        $this->assertTrue($group->exists);
        $this->assertTrue($group->wasRecentlyCreated);

        $this->assertCount(1, Group::all());
    }

    public function test_it_can_attach_members()
    {
        $groupOne = $this->createGroup(['cn' => 'Foo']);
        $groupTwo = $this->createGroup(['cn' => 'Bar']);

        $groupOne->members()->attach($groupTwo);

        $this->assertCount(1, $members = $groupOne->members()->get());
        $this->assertInstanceOf(Group::class, $member = $members->first());
        $this->assertEquals('cn=Bar,ou=Group Test OU,dc=local,dc=com', $member->getDn());
        $this->assertEquals(['cn=Foo,ou=Group Test OU,dc=local,dc=com'], $groupTwo->memberuid);
    }

    public function test_it_can_detach_members()
    {
        $groupOne = $this->createGroup(['cn' => 'Foo']);
        $groupTwo = $this->createGroup(['cn' => 'Bar']);

        $groupOne->members()->attach($groupTwo);

        $this->assertCount(1, $groupOne->members()->get());

        $groupOne->members()->detach($groupTwo);

        $this->assertCount(0, $groupOne->members()->get());
    }

    public function test_it_can_detach_or_delete()
    {
        $groupOne = $this->createGroup(['cn' => 'Foo']);
        $groupTwo = $this->createGroup(['cn' => 'Bar']);

        $groupOne->members()->attach($groupTwo);

        $this->assertTrue($groupOne->exists);

        $groupOne->members()->detachOrDeleteParent($groupTwo);

        $this->assertFalse($groupOne->exists);
    }

    public function test_it_can_detach_or_delete_with_multiple_groups()
    {
        $groupOne = $this->createGroup(['cn' => 'Foo']);
        $groupTwo = $this->createGroup(['cn' => 'Bar']);
        $groupThree = $this->createGroup(['cn' => 'Baz']);

        $groupOne->members()->attachMany([$groupTwo, $groupThree]);

        $groupOne->members()->detachOrDeleteParent($groupTwo);

        $this->assertTrue($groupOne->exists);
    }
}
