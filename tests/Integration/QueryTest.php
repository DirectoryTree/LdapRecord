<?php

namespace LdapRecord\Tests\Integration;

use Illuminate\Support\LazyCollection;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Query\Collection;
use LdapRecord\Tests\Integration\Concerns\MakesUsers;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;
use LdapRecord\Tests\Integration\Concerns\SetupTestOu;

class QueryTest extends TestCase
{
    use MakesUsers;
    use SetupTestConnection;
    use SetupTestOu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupTestOu();
    }

    public function test_it_can_paginate()
    {
        foreach (LazyCollection::range(1, 10) as $index) {
            $this->makeUser($this->ou)->save();
        }

        $this->assertCount(10, User::paginate(5));
    }

    public function test_it_can_chunk()
    {
        foreach (LazyCollection::range(1, 10) as $index) {
            $this->makeUser($this->ou)->save();
        }

        $pages = 0;

        User::chunk(5, function (Collection $results) use (&$pages) {
            $pages++;

            $this->assertCount(5, $results);
        });

        $this->assertEquals(2, $pages);
    }

    public function test_it_cannot_override_limit_when_chunking()
    {
        foreach (LazyCollection::range(1, 10) as $index) {
            $this->makeUser($this->ou)->save();
        }

        $pages = 0;

        User::limit(1)->chunk(5, function (Collection $results) use (&$pages) {
            $pages++;

            $this->assertCount(5, $results);
        });

        $this->assertEquals(2, $pages);
    }
}
