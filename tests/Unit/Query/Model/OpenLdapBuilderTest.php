<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Tests\TestCase;

class OpenLdapBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    public function test_query_always_selects_entryuuid()
    {
        $this->assertEquals(['entryuuid', '*'], Entry::query()->getSelects());
        $this->assertEquals(['entryuuid', 'objectclass'], Entry::query()->select('entryuuid')->getSelects());
    }

    public function test_where_enabled()
    {
        $b = Entry::query()->whereEnabled();

        $this->assertEquals('(!(pwdAccountLockedTime=*))', $b->getQuery()->getQuery());
    }

    public function test_where_disabled()
    {
        $b = Entry::query()->whereDisabled();

        $this->assertEquals('(pwdAccountLockedTime=*)', $b->getQuery()->getQuery());
    }
}
