<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;

class ModelAttributeAppendsTest extends TestCase
{
    public function test_accessors_are_appended()
    {
        $this->assertEquals([
            'foo' => ['bar'],
        ], (new ModelAttributeAppendsTestStub())->jsonSerialize());
    }

    public function test_get_appends()
    {
        $this->assertEquals(['foo'], (new ModelAttributeAppendsTestStub())->getAppends());
    }

    public function test_set_appends()
    {
        $model = new ModelAttributeAppendsTestStub();

        $model->setAppends(['bar']);

        $this->assertEquals(['bar'], $model->getAppends());
    }

    public function test_has_appended()
    {
        $this->assertTrue((new ModelAttributeAppendsTestStub())->hasAppended('foo'));
    }
}

class ModelAttributeAppendsTestStub extends Model
{
    protected $appends = ['foo'];

    public function getFooAttribute()
    {
        return 'bar';
    }
}
