<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\Collection;
use LdapRecord\Tests\Integration\Concerns\MakePosixUsers;
use LdapRecord\Tests\Integration\Concerns\MakesPosixGroups;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;
use LdapRecord\Tests\Integration\Concerns\SetupTestOu;
use LdapRecord\Tests\Integration\Fixtures\PosixGroup;

class PosixGroupTest extends TestCase
{
    use MakePosixUsers;
    use MakesPosixGroups;
    use SetupTestConnection;
    use SetupTestOu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTestOu();
    }

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::flush();

        parent::tearDown();
    }

    public function test_it_can_be_created()
    {
        $group = $this->makePosixGroup($this->ou);
        $group->save();

        $this->assertTrue($group->exists);
        $this->assertTrue($group->wasRecentlyCreated);

        $this->assertCount(1, PosixGroup::all());
    }

    public function test_it_can_attach_members()
    {
        $group = $this->makePosixGroup($this->ou, ['cn' => 'Foo']);
        $group->save();

        $userOne = $this->makePosixUser($this->ou, ['cn' => 'Bar']);
        $userOne->save();

        $userTwo = $this->makePosixUser($this->ou, ['cn' => 'Baz']);
        $userTwo->save();

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
        $group = $this->makePosixGroup($this->ou, ['cn' => 'Foo']);
        $group->save();

        $user = $this->makePosixUser($this->ou, ['cn' => 'Bar']);
        $user->save();

        $group->users()->attach($user);

        $this->assertCount(1, $group->users()->get());

        $group->users()->detach($user);

        $this->assertCount(0, $group->users()->get());
    }

    public function test_it_can_detach_or_delete()
    {
        $group = $this->makePosixGroup($this->ou, ['cn' => 'Foo']);
        $group->save();

        $user = $this->makePosixUser($this->ou, ['cn' => 'Bar']);
        $user->save();

        $group->users()->attach($user);

        $this->assertTrue($group->exists);

        $group->users()->detachOrDeleteParent($user);

        $this->assertFalse($group->exists);
    }

    public function test_it_can_detach_or_delete_with_multiple_users()
    {
        $group = $this->makePosixGroup($this->ou, ['cn' => 'Foo']);
        $group->save();

        $userOne = $this->makePosixUser($this->ou, ['cn' => 'Bar']);
        $userOne->save();

        $userTwo = $this->makePosixUser($this->ou, ['cn' => 'Baz']);
        $userTwo->save();

        $group->users()->attach([$userOne, $userTwo]);

        $group->users()->detachOrDeleteParent($userOne);

        $this->assertTrue($group->exists);
    }

    public function test_it_can_detach_many_users()
    {
        $group = $this->makePosixGroup($this->ou, ['cn' => 'Foo']);
        $group->save();

        $userOne = $this->makePosixUser($this->ou, ['cn' => 'Bar']);
        $userOne->save();

        $userTwo = $this->makePosixUser($this->ou, ['cn' => 'Baz']);
        $userTwo->save();

        $group->users()->attach(new Collection([$userOne, $userTwo]));

        $this->assertEquals(2, $group->users()->count());

        $group->users()->detach([$userOne, $userTwo]);

        $this->assertEquals(0, $group->users()->count());
    }
}
