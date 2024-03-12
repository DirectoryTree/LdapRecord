<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Models\Collection;
use LdapRecord\Tests\Integration\Concerns\MakesGroups;
use LdapRecord\Tests\Integration\Concerns\MakesUsers;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;
use LdapRecord\Tests\Integration\Concerns\SetupTestOu;

class GroupTest extends TestCase
{
    use MakesGroups;
    use MakesUsers;
    use SetupTestConnection;
    use SetupTestOu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTestOu();
    }

    public function test_it_associates_users()
    {
        $user = $this->makeUser($this->ou);
        $user->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate($user);
        $group->save();

        $this->assertCount(1, $members = $group->members()->get());
        $this->assertTrue($members->first()->is($user));
    }

    public function test_it_associates_groups()
    {
        $user = $this->makeUser($this->ou);
        $user->save();

        $groupOne = $this->makeGroup($this->ou);
        $groupOne->members()->associate($user);
        $groupOne->save();

        $groupTwo = $this->makeGroup($this->ou);

        $groupTwo->members()->associate($groupOne);

        $groupTwo->save();

        $this->assertCount(1, $groupTwo->members()->get());
        $this->assertCount(1, $groupOne->members()->get());
    }

    public function test_it_attaches_members()
    {
        $firstUser = $this->makeUser($this->ou);
        $firstUser->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate($firstUser);

        $this->assertEquals(
            [$firstUser->getDn()],
            $group->{$group->members()->getRelationKey()}
        );

        $group->save();

        $secondUser = $this->makeUser($this->ou);
        $secondUser->save();

        $group->members()->attach($secondUser);

        $this->assertEquals([
            $firstUser->getDn(),
            $secondUser->getDn(),
        ], $group->{$group->members()->getRelationKey()});

        $this->assertCount(2, $group->members()->get());
    }

    public function test_it_detaches_members()
    {
        $firstUser = $this->makeUser($this->ou);
        $firstUser->save();

        $secondUser = $this->makeUser($this->ou);
        $secondUser->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate(new Collection([
            $firstUser,
            $secondUser,
        ]));

        $group->save();

        $this->assertEquals([
            $firstUser->getDn(),
            $secondUser->getDn(),
        ], $group->{$group->members()->getRelationKey()});

        $this->assertCount(2, $group->members()->get());

        $group->members()->detach($secondUser);

        $this->assertEquals(
            [$firstUser->getDn()],
            $group->{$group->members()->getRelationKey()}
        );

        $group->refresh();

        $this->assertCount(1, $members = $group->members()->get());
        $this->assertTrue($members->first()->is($firstUser));
    }

    public function test_it_dissociates_members()
    {
        $firstUser = $this->makeUser($this->ou);
        $firstUser->save();

        $secondUser = $this->makeUser($this->ou);
        $secondUser->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate([$firstUser, $secondUser]);

        $this->assertEquals([
            $firstUser->getDn(),
            $secondUser->getDn(),
        ], $group->{$group->members()->getRelationKey()});

        $group->save();

        $this->assertCount(2, $group->members()->get());

        $group->members()->dissociate($secondUser);

        $this->assertEquals(
            [$firstUser->getDn()],
            $group->{$group->members()->getRelationKey()}
        );

        $group->save();

        $this->assertCount(1, $members = $group->members()->get());
        $this->assertTrue($members->first()->is($firstUser));
    }

    public function test_it_deletes_group_when_empty_with_detach_or_delete()
    {
        $user = $this->makeUser($this->ou);
        $user->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate($user);
        $group->save();

        $group->members()->detachOrDeleteParent($user);

        $this->assertFalse($group->exists);
    }
}
