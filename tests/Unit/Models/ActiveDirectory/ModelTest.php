<?php

namespace LdapRecord\Unit\Tests\Models\ActiveDirectory;

use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Query\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

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

        $this->assertEquals('S-1-5-21-1004336348-1177238915-682003330-512', $m->toArray()['objectsid'][0]);
    }

    public function test_is_deleted()
    {
        $m = new Entry(['isdeleted' => 'true']);
        $this->assertTrue($m->isDeleted());
    }

    public function test_restore_returns_false_when_object_is_not_deleted()
    {
        $this->assertFalse((new Entry())->isDeleted());
        $this->assertFalse((new Entry(['isdeleted' => 'false']))->isDeleted());
    }

    public function test_restore()
    {
        $m = (new TestModelRestoreStub())->setRawAttributes([
            'isdeleted' => ['true'],
            'dn' => ['CN=John Doe\0ADEL:0eeaf35f-a619-4435-a2c7-d99b58dfcb77,CN=Deleted Objects,DC=local,DC=com'],
        ]);

        $m->restore();

        $this->assertEquals('CN=John Doe,DC=local,DC=com', $m->getDn());
    }
}

class TestModelRestoreStub extends Entry
{
    public function refresh()
    {
        return true;
    }

    public function newQuery()
    {
        $query = m::mock(Builder::class);
        $query->shouldReceive('update')->once()->with(
            'CN=John Doe\0ADEL:0eeaf35f-a619-4435-a2c7-d99b58dfcb77,CN=Deleted Objects,DC=local,DC=com',
            [
                [
                    'attrib' => 'isdeleted',
                    'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL,
                ],
                [
                    'attrib' => 'distinguishedname',
                    'modtype' => LDAP_MODIFY_BATCH_ADD,
                    'values' => ['CN=John Doe,DC=local,DC=com'],
                ],
            ]
        )->andReturnTrue();

        return $query;
    }
}
