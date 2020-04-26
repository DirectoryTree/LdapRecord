<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\ActiveDirectory\Group;

class ModelHasOnePrimaryGroupTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        Container::getInstance()->add(new Connection());
    }

    public function test_attach_sets_users_primary_group_id()
    {
        $user = new UserSaveModelStub();

        $group = new Group(['objectsid' => 'S-1-111-222-513']);

        $this->assertEquals($group, $user->primaryGroup()->attach($group));
        $this->assertEquals('513', $user->getFirstAttribute('primarygroupid'));
    }

    public function test_detach_clears_users_primary_group_id()
    {
        $user = new UserSaveModelStub(['primarygroupid' => '513']);

        $this->assertEquals('513', $user->getFirstAttribute('primarygroupid'));

        $this->assertTrue($user->primaryGroup()->detach());
        $this->assertNull($user->getFirstAttribute('primarygroupid'));
    }
}

class UserSaveModelStub extends User
{
    public function save(array $attributes = [])
    {
        return true;
    }
}
