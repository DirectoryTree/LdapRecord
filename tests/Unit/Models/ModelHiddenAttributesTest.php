<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;

class ModelHiddenAttributesTest extends TestCase
{
    public function test_attributes_can_be_added_to_hidden_and_visible()
    {
        $m = new Entry;

        $m->setVisible(['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $m->getVisible());

        $m->setHidden(['baz', 'zal']);
        $this->assertEquals(['baz', 'zal'], $m->getHidden());

        $m->addVisible('baz');
        $this->assertEquals(['foo', 'bar', 'baz'], $m->getVisible());

        $m->addHidden(['fal']);
        $this->assertEquals(['baz', 'zal', 'fal'], $m->getHidden());
    }

    public function test_attributes_can_be_hidden()
    {
        $m = new ModelWithHiddenAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $this->assertEquals(['baz' => ['zal']], $m->toArray());
    }

    public function test_attributes_can_be_visible()
    {
        $m = new ModelWithVisibleAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $this->assertEquals(['foo' => ['bar']], $m->toArray());
    }

    public function test_attributes_can_be_made_visible()
    {
        $m = new ModelWithHiddenAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->makeVisible('foo');

        $this->assertEquals([
            'foo' => ['bar'],
            'baz' => ['zal'],
        ], $m->toArray());
    }

    public function test_attributes_can_be_made_hidden()
    {
        $m = new ModelWithVisibleAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->makeHidden('foo');

        $this->assertEquals(['baz' => ['zal']], $m->toArray());
    }

    public function test_attributes_can_be_added_as_hidden()
    {
        $m = new ModelWithHiddenAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->addHidden('baz');

        $this->assertEmpty($m->toArray());
    }

    public function test_hidden_cannot_be_overridden_and_made_visible()
    {
        $m = new ModelWithHiddenAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->setVisible(['foo']);

        $this->assertEmpty($m->toArray());
    }

    public function test_visible_can_be_overridden()
    {
        $m = new ModelWithVisibleAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->setHidden(['baz']);

        $this->assertEquals(['foo' => ['bar']], $m->toArray());
    }

    public function test_visible_attributes_can_be_added()
    {
        $m = new ModelWithVisibleAttributesStub([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->addVisible('baz');

        $this->assertEquals([
            'foo' => ['bar'],
            'baz' => ['zal'],
        ], $m->toArray());
    }

    public function test_attribute_keys_are_normalized()
    {
        $m = new Entry([
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $m->makeHidden(['FOO', 'bAz']);

        $this->assertEmpty($m->toArray());
    }
}

class ModelWithHiddenAttributesStub extends Model
{
    protected array $hidden = ['foo'];
}

class ModelWithVisibleAttributesStub extends Model
{
    protected array $visible = ['foo'];
}
