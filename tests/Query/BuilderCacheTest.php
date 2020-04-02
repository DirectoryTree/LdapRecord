<?php

namespace LdapRecord\Tests\Query;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Query\Cache;
use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;
use LdapRecord\Query\ArrayCacheStore;

class BuilderCacheTest extends TestCase
{
    public function test_cache_is_set_from_connection_onto_new_query_builders()
    {
        $store = new ArrayCacheStore();

        $conn = new Connection();
        $conn->setCache($store);

        $query = $conn->query();

        $this->assertInstanceOf(Cache::class, $query->getCache());
        $this->assertInstanceOf(ArrayCacheStore::class, $query->getCache()->store());
    }

    public function test_cache_is_set_onto_new_model_query_builders()
    {
        $store = new ArrayCacheStore();
        $conn = new Connection();
        $conn->setCache($store);

        $container = Container::getInstance();
        $container->setDefault('default');
        $container->add($conn, 'default');

        $query = Entry::query();

        $this->assertInstanceOf(Cache::class, $query->getCache());
        $this->assertInstanceOf(ArrayCacheStore::class, $query->getCache()->store());

        // Reset the container.
        $container->remove('default');
    }
}
