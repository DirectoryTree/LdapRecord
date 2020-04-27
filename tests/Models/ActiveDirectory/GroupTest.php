<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use LdapRecord\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\Group;

class GroupTest extends TestCase
{
    public function test_rid_accessor_works()
    {
        $group = new Group();
        $this->assertEquals([''], $group->rid);

        $group = new Group(['objectsid' => 'S-1-5']);
        $this->assertEquals(['5'], $group->rid);

        $group = new Group(['objectsid' => 'S-1-5-513']);
        $this->assertEquals(['513'], $group->rid);

        $group = new Group(['objectsid' => 'S-1-5-2141378235-513']);
        $this->assertEquals(['513'], $group->rid);
    }
}
