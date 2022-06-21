<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Tests\Integration\Fixtures\Group;

class GroupTest extends TestCase
{
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

        Container::reset();

        parent::tearDown();
    }

    public function test_it_can_be_created()
    {
        $group = (new Group([
            'cn' => 'Foo',
            'gidNumber' => 500,
        ]))->inside($this->ou);

        $group->save();

        $this->assertTrue($group->exists);
        $this->assertTrue($group->wasRecentlyCreated);

        $this->assertCount(1, Group::all());
    }

    public function test_it_can_attach_members()
    {
        $groupOne = (new Group([
            'cn' => 'Foo',
            'gidNumber' => 500,
        ]))->inside($this->ou);

        $groupOne->save();

        $groupTwo = (new Group([
            'cn' => 'Bar',
            'gidNumber' => 500,
        ]))->inside($this->ou);

        $groupTwo->save();

        $groupOne->members()->attach($groupTwo);

        $this->assertCount(1, $members = $groupOne->members()->get());
        $this->assertInstanceOf(Group::class, $member = $members->first());
        $this->assertEquals('cn=Bar,ou=Group Test OU,dc=local,dc=com', $member->getDn());
        $this->assertEquals(['cn=Foo,ou=Group Test OU,dc=local,dc=com'], $groupTwo->memberuid);
    }

    public function test_it_can_detach_members()
    {
        $groupOne = (new Group([
            'cn' => 'Foo',
            'gidNumber' => 500,
        ]))->inside($this->ou);

        $groupOne->save();

        $groupTwo = (new Group([
            'cn' => 'Bar',
            'gidNumber' => 500,
        ]))->inside($this->ou);

        $groupTwo->save();

        $groupOne->members()->attach($groupTwo);

        $this->assertCount(1, $groupOne->members()->get());

        $groupOne->members()->detach($groupTwo);

        $this->assertCount(0, $groupOne->members()->get());
    }
}
