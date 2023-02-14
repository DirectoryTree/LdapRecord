<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;

class ModelGuidTest extends TestCase
{
    public function test_get_object_guid()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $m = new Entry(['objectguid' => $guid]);

        $this->assertEquals($guid, $m->getObjectGuid());
    }

    public function test_get_object_guid_binary()
    {
        $hex = 'd0b40d279d24a7469cc5eb695d9af9ac';

        $m = new Entry(['objectguid' => hex2bin($hex)]);

        $this->assertEquals(hex2bin($hex), $m->getObjectGuid());
        $this->assertEquals('270db4d0-249d-46a7-9cc5-eb695d9af9ac', $m->getConvertedGuid());
    }

    public function test_object_guid_is_converted()
    {
        $hex = 'd0b40d279d24a7469cc5eb695d9af9ac';

        $m = new Entry(['objectguid' => hex2bin($hex)]);

        $this->assertEquals('270db4d0-249d-46a7-9cc5-eb695d9af9ac', $m->toArray()['objectguid'][0]);
    }
}
