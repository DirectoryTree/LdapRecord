<?php

namespace LdapRecord\Tests\Unit\Models\ActiveDirectory;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelTest extends TestCase
{
    public function test_get_object_guid()
    {
        $this->assertNull((new Entry)->getObjectGuid());

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

    public function test_get_converted_guid_with_empty_values()
    {
        $this->assertNull((new Entry(['objectguid' => null]))->getConvertedGuid());
        $this->assertNull((new Entry(['objectguid' => '']))->getConvertedGuid());
        $this->assertNull((new Entry(['objectguid' => ' ']))->getConvertedGuid());
    }

    public function test_get_object_sid()
    {
        $this->assertNull((new Entry)->getObjectSid());

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

    public function test_get_converted_sid_with_empty_values()
    {
        $this->assertNull((new Entry(['objectsid' => null]))->getConvertedSid());
        $this->assertNull((new Entry(['objectsid' => '']))->getConvertedSid());
        $this->assertNull((new Entry(['objectsid' => ' ']))->getConvertedSid());
    }

    public function test_is_deleted()
    {
        $m = new Entry(['isdeleted' => 'true']);
        $this->assertTrue($m->isDeleted());
    }

    public function test_restore_returns_false_when_object_is_not_deleted()
    {
        $this->assertFalse((new Entry)->isDeleted());
        $this->assertFalse((new Entry(['isdeleted' => 'false']))->isDeleted());
    }

    public function test_restore()
    {
        $m = (new TestModelRestoreStub)->setRawAttributes([
            'isdeleted' => ['true'],
            'dn' => ['CN=John Doe\0ADEL:0eeaf35f-a619-4435-a2c7-d99b58dfcb77,CN=Deleted Objects,DC=local,DC=com'],
        ]);

        $m->restore();

        $this->assertEquals('CN=John Doe,DC=local,DC=com', $m->getDn());
    }

    public function test_relation_query_can_be_created()
    {
        Container::addConnection(new Connection);

        $entry = new class extends Entry
        {
            public function relation(): HasMany
            {
                return $this->hasMany(Entry::class, 'dn');
            }
        };

        /** @var HasMany $relation */
        $relation = $entry->relation()->whereIn('foo', ['foo']);

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(ActiveDirectoryBuilder::class, $query = $relation->getQuery());
        // With a single value, whereIn produces a single Equals filter (no OR wrapper needed)
        $this->assertEquals('(foo=\66\6f\6f)', $query->getQuery()->getQuery());
    }
}

class TestModelRestoreStub extends Entry
{
    public function refresh(): bool
    {
        return true;
    }

    public function newQuery(): Builder
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
