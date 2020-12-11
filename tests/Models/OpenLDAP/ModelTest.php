<?php

namespace LdapRecord\Tests\Models\OpenLDAP;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Tests\TestCase;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\OpenLDAP\Scopes\AddEntryUuidToSelects;

class ModelTest extends TestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        Entry::clearBootedModels();
    }

    public function test_entry_uuid_is_always_added_to_select_list_with_asterisk_when_no_selects_have_been_added()
    {
        Container::addConnection(new Connection());

        $model = new Entry();

        $query = $model->newQuery();

        $query->getQuery();

        $this->assertEquals(['*', $model->getGuidKey()], $query->getSelects());
        $this->assertInstanceOf(AddEntryUuidToSelects::class, $query->appliedScopes()[AddEntryUuidToSelects::class]);
    }

    public function test_entry_uuid_is_added_to_a_select_list_without_asterisk_when_selects_have_been_added()
    {
        Container::addConnection(new Connection());

        $model = new Entry();

        $query = $model->newQuery()->select(['foo']);

        $query->getQuery();

        $this->assertEquals(['foo', $model->getGuidKey(), 'objectclass'], $query->getSelects());
        $this->assertInstanceOf(AddEntryUuidToSelects::class, $query->appliedScopes()[AddEntryUuidToSelects::class]);
    }
}
