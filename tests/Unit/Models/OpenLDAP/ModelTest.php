<?php

namespace LdapRecord\Tests\Unit\Models\OpenLDAP;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Tests\TestCase;

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Entry::clearBootedModels();
    }

    public function test_entry_uuid_is_always_added_to_select_list_with_asterisk_when_no_selects_have_been_added()
    {
        Container::addConnection(new Connection);

        $model = new Entry;

        $query = $model->newQuery();

        $this->assertEquals([$model->getGuidKey(), '*'], $query->getSelects());
    }

    public function test_entry_uuid_is_added_to_a_select_list_without_asterisk_when_selects_have_been_added()
    {
        Container::addConnection(new Connection);

        $model = new Entry;

        $query = $model->newQuery()->select('foo');

        $this->assertEquals([$model->getGuidKey(), 'foo', 'objectclass'], $query->getSelects());
    }
}
