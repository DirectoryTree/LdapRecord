<?php

namespace LdapRecord\Tests\Unit\Models\ActiveDirectory;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Tests\TestCase;

class ModelHasOnePrimaryGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    public function test_attach_sets_users_primary_group_id()
    {
        $user = new UserSaveModelStub;

        $group = new Group(['objectsid' => 'S-1-111-222-513']);

        $user->primaryGroup()->attach($group);

        $this->assertEquals('513', $user->getFirstAttribute('primarygroupid'));
    }

    public function test_detach_clears_users_primary_group_id()
    {
        $user = new UserSaveModelStub(['primarygroupid' => '513']);

        $this->assertEquals('513', $user->getFirstAttribute('primarygroupid'));

        $user->primaryGroup()->detach();

        $this->assertNull($user->getFirstAttribute('primarygroupid'));
    }
}

class UserSaveModelStub extends User
{
    public function save(array $attributes = []): void {}
}
