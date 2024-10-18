<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;

class ModelAttributeAppendsTest extends TestCase
{
    public function test_accessors_are_appended()
    {
        $this->assertEquals([
            'foo' => ['bar'],
        ], (new ModelAttributeAppendsTestStub)->toArray());
    }

    public function test_get_appends()
    {
        $this->assertEquals(['foo'], (new ModelAttributeAppendsTestStub)->getAppends());
    }

    public function test_set_appends()
    {
        $model = new ModelAttributeAppendsTestStub;

        $model->setAppends(['bar']);

        $this->assertEquals(['bar'], $model->getAppends());
    }

    public function test_has_appended()
    {
        $this->assertTrue((new ModelAttributeAppendsTestStub)->hasAppended('foo'));
    }

    public function test_appends_with_hyphenated_property()
    {
        $model = new ModelAttributeAppendsTestStub;

        $model->setAppends(['foo-bar']);

        $this->assertEquals([
            'foo-bar' => ['foo-bar'],
        ], $model->toArray());
    }
}

class ModelAttributeAppendsTestStub extends Model
{
    protected array $appends = ['foo'];

    public function getFooAttribute(): string
    {
        return 'bar';
    }

    public function getFooBarAttribute(): string
    {
        return 'foo-bar';
    }
}
