<?php

namespace LdapRecord\Tests\Unit\Query;

use Carbon\Carbon;
use LdapRecord\Query\ArrayCacheStore;
use LdapRecord\Tests\TestCase;

class ArrayCacheStoreTest extends TestCase
{
    public function test_get_returns_default_value()
    {
        $this->assertNull((new ArrayCacheStore)->get('invalid'));

        $value = (new ArrayCacheStore)->get('invalid', 'value');

        $this->assertEquals('value', $value);
    }

    public function test_set_stores_value()
    {
        $store = new ArrayCacheStore;

        $this->assertTrue($store->set('key', 'value'));

        $this->assertEquals('value', $store->get('key'));
    }

    public function test_set_stores_values_without_ttl_indefinitely()
    {
        $store = new ArrayCacheStore;

        $store->set('key', 'value');

        $this->assertEquals('value', $store->get('key'));
        $this->assertEquals('value', $store->get('key'));
        $this->assertEquals('value', $store->get('key'));
    }

    public function test_set_keys_with_expiry_return_default_value_when_expired()
    {
        Carbon::setTestNow(Carbon::now());

        $store = new ArrayCacheStore;

        $store->set('key', 'value', 10);
        $this->assertEquals('value', $store->get('key', 'foo'));

        Carbon::setTestNow(Carbon::now()->addSeconds(10)->addSecond());
        $this->assertEquals('foo', $store->get('key', 'foo'));

        Carbon::setTestNow();
    }

    public function test_set_multiple__stores_values()
    {
        $store = new ArrayCacheStore;

        $this->assertTrue(
            $store->setMultiple([
                'foo' => 'bar',
                'baz' => 'zar',
            ])
        );

        $this->assertEquals('bar', $store->get('foo'));
        $this->assertEquals('zar', $store->get('baz'));
    }

    public function test_get_multiple_returns_stored_values()
    {
        $store = new ArrayCacheStore;

        $store->set('foo', 'bar');
        $store->set('baz', 'zar');

        $this->assertEquals([
            'foo' => 'bar',
            'baz' => 'zar',
        ], $store->getMultiple(['foo', 'baz']));

        $this->assertEquals([
            'foo' => 'bar',
            'zal' => 'value',
        ], $store->getMultiple(['foo', 'zal'], 'value'));
    }

    public function test_delete_removes_item_from_storage()
    {
        $store = new ArrayCacheStore;

        $store->set('key', 'value');

        $this->assertEquals('value', $store->get('key'));

        $this->assertTrue($store->delete('key'));
        $this->assertNull($store->get('key'));
    }

    public function test_delete_multiple_removes_items_from_storage()
    {
        $store = new ArrayCacheStore;

        $store->set('foo', 'bar');
        $store->set('baz', 'zal');
        $store->set('zar', 'fal');

        $this->assertTrue($store->deleteMultiple(['foo', 'baz']));

        $this->assertNull($store->get('foo'));
        $this->assertNull($store->get('baz'));
        $this->assertEquals('fal', $store->get('zar'));
    }

    public function test_clear_removes_all_items_from_storage()
    {
        $store = new ArrayCacheStore;

        $store->setMultiple(['foo' => 'bar', 'baz' => 'zal']);

        $this->assertEquals([
            'foo' => 'bar',
            'baz' => 'zal',
        ], $store->getMultiple(['foo', 'baz']));

        $store->clear();

        $this->assertNull($store->get('foo'));
        $this->assertNull($store->get('baz'));
    }

    public function test_has_detects_items_in_storage()
    {
        $store = new ArrayCacheStore;

        $store->setMultiple(['foo' => 'bar', 'baz' => 'zal']);

        $this->assertTrue($store->has('foo'));
        $this->assertTrue($store->has('baz'));
        $this->assertFalse($store->has('fal'));
    }
}
