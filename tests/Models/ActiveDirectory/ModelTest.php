<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use LdapRecord\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\Entry;

class ModelTest extends TestCase
{
    public function test_get_object_sid()
    {
        $sid = 'S-1-5-21-977923109-2952828257-175163757-387119';

        $m = new Entry(['objectsid' => $sid]);

        $this->assertEquals($sid, $m->getObjectSid());
    }

    public function test_get_object_sid_binary()
    {
        $hex = '010500000000000515000000dcf4dc3b833d2b46828ba62800020000';

        $m = new Entry(['objectsid' => hex2bin($hex)]);

        $this->assertEquals(hex2bin($hex), $m->getObjectSid());
        $this->assertEquals('S-1-5-21-1004336348-1177238915-682003330-512', $m->getConvertedSid());
    }

    public function test_object_sid_is_converted()
    {
        $hex = '010500000000000515000000dcf4dc3b833d2b46828ba62800020000';

        $m = new Entry(['objectsid' => hex2bin($hex)]);

        $this->assertEquals('S-1-5-21-1004336348-1177238915-682003330-512', $m->jsonSerialize()['objectsid'][0]);
    }
}