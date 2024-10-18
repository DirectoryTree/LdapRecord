<?php

namespace LdapRecord\Tests\Unit\Query;

use Carbon\Carbon;
use LdapRecord\Query\ArrayCacheStore;
use LdapRecord\Query\Cache;
use LdapRecord\Tests\TestCase;

class CacheTest extends TestCase
{
    public function test_cache_can_be_given_array_store()
    {
        $cache = new Cache(new ArrayCacheStore);

        $this->assertInstanceOf(ArrayCacheStore::class, $cache->store());
    }

    public function test_get_returns_null_by_default()
    {
        $cache = new Cache(new ArrayCacheStore);

        $this->assertNull($cache->get('invalid'));
    }

    public function test_items_can_be_put()
    {
        $cache = new Cache(new ArrayCacheStore);

        $this->assertTrue($cache->put('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));

        $this->assertTrue($cache->put('foo', 'bar', Carbon::now()->addSecond()));

        sleep(2);

        $this->assertNull($cache->get('foo'));
    }

    public function test_items_can_be_deleted()
    {
        $cache = new Cache(new ArrayCacheStore);

        $cache->put('test', 'test');

        $this->assertTrue($cache->put('foo', 'bar', Carbon::now()->addMinute()));
        $this->assertEquals('bar', $cache->get('foo'));

        $cache->delete('foo');
        $this->assertNull($cache->get('foo'));
    }

    public function test_remember_executes_closure_and_stores_value()
    {
        $cache = new Cache(new ArrayCacheStore);

        $cache->remember('foo', Carbon::now()->addMinute(), function () {
            return 'bar';
        });

        $this->assertEquals('bar', $cache->get('foo'));

        $cache->remember('baz', 1, function () {
            return 'zal';
        });

        sleep(2);

        $this->assertNull($cache->get('baz'));
    }
}
