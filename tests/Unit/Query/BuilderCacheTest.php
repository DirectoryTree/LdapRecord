<?php

namespace LdapRecord\Tests\Unit\Query;

use Carbon\Carbon;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapResultResponse;
use LdapRecord\Models\Entry;
use LdapRecord\Query\ArrayCacheStore;
use LdapRecord\Query\Cache;
use LdapRecord\Testing\ConnectionFake;
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

        $cache->shouldReceive('get')->with(m::type('string'))->once()->andReturn([]);

        $this->assertEmpty($query->get());
    }

    public function test_cached_queries_distinguish_configured_base_dns()
    {
        $cache = new ArrayCacheStore;

        $first = ConnectionFake::make(['base_dn' => 'ou=one,dc=example'])->shouldBeConnected();
        $second = ConnectionFake::make(['base_dn' => 'ou=two,dc=example'])->shouldBeConnected();

        $first->setCache($cache);
        $second->setCache($cache);

        $first->getLdapConnection()->setHost('localhost');
        $second->getLdapConnection()->setHost('localhost');

        $first->getLdapConnection()->expect(
            LdapFake::operation('search')->with('ou=one,dc=example', '(objectclass=*)', ['*'], false, 0)
                ->once()->andReturn($firstResults = [['dn' => 'cn=John,ou=one,dc=example']])
        );

        $second->getLdapConnection()->expect(
            LdapFake::operation('search')->with('ou=two,dc=example', '(objectclass=*)', ['*'], false, 0)
                ->once()->andReturn($secondResults = [['dn' => 'cn=Jane,ou=two,dc=example']])
        );

        $this->assertSame($firstResults, $first->query()->cache()->get());
        $this->assertSame($secondResults, $second->query()->cache()->get());

        $first->tearDown();
        $second->tearDown();
    }

    public function test_implicit_and_explicit_search_bases_share_cached_results()
    {
        $connection = ConnectionFake::make(['base_dn' => 'dc=example'])->shouldBeConnected();

        $connection->setCache(new ArrayCacheStore);

        $connection->getLdapConnection()->expect(
            LdapFake::operation('search')->once()->andReturn($results = [['dn' => 'cn=John,dc=example']])
        );

        $this->assertSame($results, $connection->query()->cache()->get());
        $this->assertSame($results, $connection->query()->in('dc=example')->cache()->get());

        $connection->tearDown();
    }

    public function test_cached_queries_distinguish_virtual_list_view_pages()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();

        $connection->setCache(new ArrayCacheStore);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn($first = [['dn' => 'cn=John,dc=example']]),
            LdapFake::operation('search')->once()->andReturn($second = [['dn' => 'cn=Jane,dc=example']]),
        ]);

        $this->assertSame($first, $connection->query()->cache()->forPage(1, 1));
        $this->assertSame($second, $connection->query()->cache()->forPage(2, 1));
        $this->assertSame($first, $connection->query()->cache()->forPage(1, 1));

        $connection->tearDown();
    }

    public function test_cached_queries_distinguish_sort_controls()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();

        $connection->setCache(new ArrayCacheStore);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn($ascending = [
                ['dn' => 'cn=Jane,dc=example'],
                ['dn' => 'cn=John,dc=example'],
            ]),
            LdapFake::operation('search')->once()->andReturn($descending = array_reverse($ascending)),
        ]);

        $this->assertSame($ascending, $connection->query()->orderBy('cn')->cache()->get());
        $this->assertSame($descending, $connection->query()->orderByDesc('cn')->cache()->get());
        $this->assertSame($ascending, $connection->query()->orderBy('cn')->cache()->get());

        $connection->tearDown();
    }

    public function test_cached_queries_distinguish_attribute_lists_with_the_same_concatenation()
    {
        $connection = ConnectionFake::make()->shouldBeConnected();

        $connection->setCache(new ArrayCacheStore);

        $connection->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn($first = [['ab' => ['one'], 'c' => ['two']]]),
            LdapFake::operation('search')->once()->andReturn($second = [['a' => ['three'], 'bc' => ['four']]]),
        ]);

        $this->assertSame($first, $connection->query()->select(['ab', 'c'])->cache()->get());
        $this->assertSame($second, $connection->query()->select(['a', 'bc'])->cache()->get());

        $connection->tearDown();
    }

    public function test_explicit_cache_keys_are_used_without_query_identity()
    {
        $connection = ConnectionFake::make(['base_dn' => 'dc=example'])->shouldBeConnected();

        $connection->setCache($cache = new ArrayCacheStore);

        $cache->set('custom-key', $results = [['dn' => 'cn=John,dc=example']]);

        $this->assertSame($results, $connection->query()->cache(key: 'custom-key')->forPage(2, 1));
    }
}
