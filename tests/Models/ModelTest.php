<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Tests\TestCase;
use LdapRecord\ContainerException;
use LdapRecord\Models\BatchModification;

class ModelTest extends TestCase
{
    public function setUp()
    {
        // Flush container instance.
        Container::getNewInstance();
    }

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

    public function test_getting_and_setting_dn()
    {
        $model = new Entry();
        $model->setDn('foo');
        $this->assertEquals('foo', $model->getDn());
        $this->assertEquals('foo', (string) $model);
    }

    public function test_creatable_dn()
    {
        Container::getNewInstance()->add(new Connection([
            'base_dn' => 'dc=acme,dc=org',
        ]));

        $model = new Entry();
        $model->cn = 'foo';
        $this->assertEquals('cn=foo,dc=acme,dc=org', (string) $model->getCreatableDn());

        $model = new Entry();
        $this->assertEquals('dc=acme,dc=org', (string) $model->getCreatableDn());
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
            'foo'   => [
                'count' => 1,
                'bar'   => [
                    'count' => 1,
                    'baz'   => [
                        'count' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertEquals([
            'foo' => [
                'bar' => [
                    'baz' => [],
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

        $this->assertFalse($model->isDirty('foo'));
        $this->assertTrue($model->isDirty('bar'));
        $this->assertTrue($model->isDirty('baz'));
        $this->assertTrue($model->isDirty('other'));
        $this->assertEquals([
            'bar'   => [20],
            'baz'   => [30],
            'other' => [40],
        ], $model->getDirty());
    }

    public function test_serialization()
    {
        $model = new Entry(['foo' => 'bar']);
        $this->assertEquals(['foo' => ['bar']], $model->jsonSerialize());

        $model->objectguid = 'bf9679e7-0de6-11d0-a285-00aa003049e2';
        $this->assertEquals([
            'foo'        => ['bar'],
            'objectguid' => ['bf9679e7-0de6-11d0-a285-00aa003049e2'],
        ], $model->jsonSerialize());
        $this->assertEquals('{"foo":["bar"],"objectguid":["bf9679e7-0de6-11d0-a285-00aa003049e2"]}', json_encode($model->jsonSerialize()));
    }

    public function test_convert()
    {
        $model = new Entry(['foo' => 'bar']);
        $model->setDn('baz');
        $model->setConnection('other');

        $converted = $model->convert(new Entry());
        $this->assertEquals($model, $converted);
        $this->assertEquals('baz', $converted->getDn());
        $this->assertEquals('other', $converted->getConnectionName());

        $model = new Entry(['foo' => 'bar']);
        $model->setDn('foo');
        $model->setRawAttributes(['bar' => 'baz']);

        $converted = $model->convert(new Entry());
        $this->assertTrue($converted->exists);
        $this->assertEquals($model, $converted);
    }

    public function test_hydrate()
    {
        $records = [
            [
                'dn'  => 'baz',
                'foo' => 'bar',
            ],
            [
                'dn'  => 'foo',
                'bar' => 'baz',
            ],
        ];

        $model = new Entry();
        $model->setConnection('other');

        $collection = $model->hydrate($records);

        $this->assertTrue($collection->first()->exists);
        $this->assertEquals('baz', $collection->first()->getDn());
        $this->assertEquals($collection->first()->getConnectionName(), 'other');

        $this->assertTrue($collection->last()->exists);
        $this->assertEquals('foo', $collection->last()->getDn());
        $this->assertEquals($collection->last()->getConnectionName(), 'other');
    }

    public function test_add_modification()
    {
        $model = new Entry();
        $model->addModification(['attrib' => 'foo', 'values' => ['bar'], 'modtype' => 3]);
        $this->assertEquals([['attrib' => 'foo', 'values' => ['bar'], 'modtype' => 3]], $model->getModifications());

        $model = new Entry();
        $model->addModification(new BatchModification('foo', 3, ['bar']));
        $this->assertEquals([['attrib' => 'foo', 'values' => ['bar'], 'modtype' => 3]], $model->getModifications());
    }

    public function test_add_modification_without_attrib()
    {
        $model = new Entry();
        $this->expectException(\InvalidArgumentException::class);
        $model->addModification(['values' => ['Changed'], 'modtype' => 3]);
    }

    public function test_add_modification_without_modtype()
    {
        $model = new Entry();
        $this->expectException(\InvalidArgumentException::class);
        $model->addModification(['attrib' => 'foo', 'values' => ['bar']]);
    }

    public function test_add_modification_without_values()
    {
        $model = new Entry();
        $model->addModification(['attrib' => 'foo', 'modtype' => 3]);
        $this->assertEquals([['attrib' => 'foo', 'modtype' => 3]], $model->getModifications());
    }

    public function test_set_modifications()
    {
        $model = new Entry();
        $model->setModifications([
            ['attrib' => 'foo', 'modtype' => 3, 'values' => ['bar']],
            new BatchModification('bar', 3, ['baz']),
        ]);

        $this->assertEquals([
            ['attrib' => 'foo', 'modtype' => 3, 'values' => ['bar']],
            ['attrib' => 'bar', 'modtype' => 3, 'values' => ['baz']],
        ], $model->getModifications());
    }

    public function test_modifications_are_created_from_dirty()
    {
        $model = new Entry();
        $model->setRawAttributes([
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'name'           => ['Name'],
        ]);

        $model->cn = null;
        $model->samaccountname = 'Changed';
        $model->test = 'New Attribute';

        $modifications = $model->getModifications();

        // Removed 'cn' attribute
        $this->assertEquals('cn', $modifications[0]['attrib']);
        $this->assertFalse(isset($modifications[0]['values']));
        $this->assertEquals(18, $modifications[0]['modtype']);

        // Modified 'samaccountname' attribute
        $this->assertEquals('samaccountname', $modifications[1]['attrib']);
        $this->assertEquals(['Changed'], $modifications[1]['values']);
        $this->assertEquals(3, $modifications[1]['modtype']);

        // New 'test' attribute
        $this->assertEquals('test', $modifications[2]['attrib']);
        $this->assertEquals(['New Attribute'], $modifications[2]['values']);
        $this->assertEquals(1, $modifications[2]['modtype']);
    }

    public function test_in_ou()
    {
        $model = new Entry();
        $this->assertFalse($model->inOu(null));
        $this->assertFalse($model->inOu(''));

        $model->setDn('cn=foo,ou=bar,dc=acme,dc=org');
        $this->assertFalse($model->inOu('foo'));
        $this->assertTrue($model->inOu('bar'));
        $this->assertTrue($model->inOu('BAR'));
        $this->assertTrue($model->inOu('BaR'));
        $this->assertFalse($model->inOu('bar,'));
        $this->assertFalse($model->inOu('ou=bar'));

        $ou = new Entry();
        $ou->setDn('ou=bar,dc=acme,dc=org');
        $this->assertTrue($model->inOu($ou));

        $ou->setDn('Ou=BaR,dc=acme,dc=org');
        $this->assertTrue($model->inOu($ou));
    }
}
