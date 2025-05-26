<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\FreeIPA\Entry;
use LdapRecord\Tests\TestCase;

class FreeIpaBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    public function test_query_always_selects_ipauniqueid()
    {
        $this->assertEquals(['ipauniqueid', '*'], Entry::query()->getSelects());
        $this->assertEquals(['ipauniqueid', 'objectclass'], Entry::query()->select('ipauniqueid')->getSelects());
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
