<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;
use LdapRecord\Connections\ContainerException;

class ModelTest extends TestCase
{
    public function test_model_must_have_default_connection()
    {
        $model = new Entry();
        $this->assertFalse($model->exists);
        $this->expectException(ContainerException::class);
        $model->getConnection();
    }

    public function test_fill()
    {
        $this->assertEmpty((new Entry())->getDn());
        $this->assertEmpty((new Entry())->getAttributes());
        $this->assertNull((new Entry())->getAttribute(null));
        $this->assertEquals(['foo' => ['bar']], (new Entry(['foo' => 'bar']))->getAttributes());
        $this->assertEquals(['bar' => ['baz']], (new Entry())->fill(['bar' => 'baz'])->getAttributes());
        $this->assertEquals(2, ((new Entry())->fill(['foo' => 'bar', 'baz' => 'foo'])->countAttributes()));
    }

    public function test_raw_attribute_filling_sets_dn()
    {
        $model = new Entry();

        $model->setRawAttributes(['dn' => 'bar']);
        $this->assertTrue($model->exists);
        $this->assertEquals('bar', $model->getDn());

        $model->setRawAttributes(['dn' => ['baz']]);
        $this->assertEquals('baz', $model->getDn());
        $this->assertEmpty($model->getAttributes());
    }

    public function test_raw_attribute_filling_sets_original()
    {
        $model = new Entry();
        $model->setRawAttributes(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $model->getOriginal());
    }

    public function test_raw_attribute_filling_removes_count_keys_recursively()
    {
        $model = new Entry();

        $model->setRawAttributes([
            'count' => 1,
            'foo' => [
                'count' => 1,
                'bar' => [
                    'count' => 1,
                    'baz' => [
                        'count' => 1
                    ],
                ],
            ],
        ]);

        $this->assertEquals([
            'foo' => [
                'bar' => [
                    'baz' => []
                ],
            ],
        ], $model->getAttributes());
    }

    public function test_attribute_manipulation()
    {
        $model = new Entry();
        $model->cn = 'foo';
        $this->assertEquals(['foo'], $model->cn);
        $this->assertTrue(isset($model->cn));
        unset($model->cn);
        $this->assertFalse(isset($model->cn));

        $model->setAttribute('bar', 1);
        $model->setFirstAttribute('baz', 2);
        $this->assertEquals([1], $model->getAttribute('bar'));
        $this->assertEquals([2], $model->getAttribute('baz'));
    }

    public function test_attribute_keys_are_normalized()
    {
        $model = new Entry();
        $model->FOO = 1;
        $model->BARbAz = 2;
        $this->assertEquals([1], $model->foo);
        $this->assertEquals([1], $model->getAttribute('foo'));
        $this->assertEquals([2], $model->barbaz);
        $this->assertEquals([2], $model->getAttribute('barbaz'));
    }

    public function test_dirty_attributes()
    {
        $model = new Entry(['foo' => 1, 'bar' => 2, 'baz' => 3]);
        $model->syncOriginal();
        $model->foo = 1;
        $model->bar = 20;
        $model->baz = 30;
        $model->other = 40;

        $this->assertEquals([
            'bar' => [20],
            'baz' => [30],
            'other' => [40]
        ], $model->getDirty());
    }
}
