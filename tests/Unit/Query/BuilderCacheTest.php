<?php

namespace LdapRecord\Tests\Unit\Query;

use Carbon\Carbon;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapResultResponse;
use LdapRecord\Models\Entry;
use LdapRecord\Query\ArrayCacheStore;
use LdapRecord\Query\Cache;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class BuilderCacheTest extends TestCase
{
    public function test_cache_is_set_from_connection_onto_new_query_builders()
    {
        $conn = new Connection;

        $conn->setCache(new ArrayCacheStore);

        $query = $conn->query();

        $this->assertInstanceOf(Cache::class, $query->getCache());
        $this->assertInstanceOf(ArrayCacheStore::class, $query->getCache()->store());
    }

    public function test_cache_is_set_onto_new_model_query_builders()
    {
        $conn = new Connection;

        $conn->setCache(new ArrayCacheStore);

        $container = Container::getInstance();
        $container->setDefaultConnection('default');
        $container->addConnection($conn, 'default');

        $query = Entry::query();

        $this->assertInstanceOf(Cache::class, $query->getCache());
        $this->assertInstanceOf(ArrayCacheStore::class, $query->getCache()->store());
    }

    public function test_cache_key_generation_connects_to_server_when_not_connected()
    {
        $ldap = (new LdapFake)->expect(
            LdapFake::operation('bind')->andReturn(new LdapResultResponse)
        );

        $ldap->setHost($host = 'localhost');

        $ldap->bind();

        $conn = new Connection(ldap: $ldap);

        $conn->setCache(
            $cache = m::mock(ArrayCacheStore::class)
        );

        $container = Container::getInstance();
        $container->setDefaultConnection('default');
        $container->addConnection($conn, 'default');

        $query = Entry::cache(Carbon::now()->addDay());

        $expectedKey = md5(implode([
            $host,
            $query->getType(),
            $query->getDn(),
            $query->getUnescapedQuery(),
            implode($query->getSelects()),
            $query->getQuery()->limit,
            $query->getQuery()->paginated,
        ]));

        $cache->shouldReceive('get')->with($expectedKey)->andReturn([]);

        $this->assertEmpty($query->get());
    }
}
