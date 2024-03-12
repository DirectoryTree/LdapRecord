<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Tests\Integration\Concerns\MakesGroups;
use LdapRecord\Tests\Integration\Concerns\MakesUsers;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;
use LdapRecord\Tests\Integration\Concerns\SetupTestOu;

class UserTest extends TestCase
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

    public function test_it_can_attach_groups()
    {
        $firstUser = $this->makeUser($this->ou);
        $firstUser->save();

        $secondUser = $this->makeUser($this->ou);
        $secondUser->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate($firstUser);
        $group->save();

        $secondUser->groups()->attach($group);

        $this->assertCount(1, $secondUser->groups()->get());
        $this->assertCount(2, $group->members()->get());
    }

    public function test_it_can_detach_groups()
    {
        $firstUser = $this->makeUser($this->ou);
        $firstUser->save();

        $secondUser = $this->makeUser($this->ou);
        $secondUser->save();

        $group = $this->makeGroup($this->ou);
        $group->members()->associate([$firstUser, $secondUser]);
        $group->save();

        $this->assertCount(1, $firstUser->groups()->get());

        $firstUser->groups()->detach($group);

        $this->assertCount(0, $firstUser->groups()->get());
        $this->assertCount(1, $secondUser->groups()->get());
        $this->assertCount(1, $group->members()->get());
    }
}
