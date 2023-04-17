<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\Model;
use LdapRecord\Query\Collection;
use LdapRecord\Tests\TestCase;

class ModelAttributeCastTest extends TestCase
{
    public function test_boolean_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['TRUE']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['FALSE']]);
        $this->assertFalse($model->boolAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['booleanAttribute' => ['FALSE']]);
        $this->assertFalse($model->booleanAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['booleanAttribute' => ['FALSE']]);
        $this->assertFalse($model->booleanAttribute);

        // Casing differences

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['true']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['false']]);
        $this->assertFalse($model->boolAttribute);

        // Variable differences

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['invalid']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => ['']]);
        $this->assertFalse($model->boolAttribute);

        $model = (new ModelCastStub())->setRawAttributes(['boolAttribute' => []]);
        $this->assertNull($model->boolAttribute);

        $model = (new ModelCastStub());
        $this->assertNull($model->boolAttribute);
    }

    public function test_float_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['floatAttribute' => ['12345.6789']]);

        $this->assertIsFloat($value = $model->floatAttribute);
        $this->assertEquals(12345.6789, $value);
    }

    public function test_double_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['doubleAttribute' => ['1234.567']]);

        $this->assertIsFloat($value = $model->doubleAttribute);
        $this->assertEquals(1234.567, $value);
    }

    public function test_object_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['objectAttribute' => ['{"foo": 1, "bar": "two"}']]);

        $this->assertIsObject($value = $model->objectAttribute);

        $object = (new \stdClass());
        $object->foo = 1;
        $object->bar = 'two';

        $this->assertEquals($object, $value);
    }

    public function test_json_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['jsonAttribute' => ['{"foo": 1, "bar": "two"}']]);

        $this->assertEquals(['foo' => 1, 'bar' => 'two'], $model->jsonAttribute);
    }

    public function test_collection_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['collectionAttribute' => ['foo' => 1, 'bar' => 'two']]);

        $this->assertInstanceOf(Collection::class, $collection = $model->collectionAttribute);

        $this->assertEquals(['foo' => 1, 'bar' => 'two'], $collection->toArray());
    }

    public function test_integer_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes([
            'intAttribute' => ['1234.5678'],
            'integerAttribute' => ['1234.5678'],
        ]);

        $this->assertIsInt($model->intAttribute);
        $this->assertIsInt($model->integerAttribute);

        $this->assertEquals(1234, $model->intAttribute);
        $this->assertEquals(1234, $model->integerAttribute);
    }

    public function test_decimal_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['decimalAttribute' => ['1234.5678']]);

        $this->assertIsString($value = $model->decimalAttribute);

        $this->assertEquals(1234.57, $value);
    }

    public function test_ldap_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['ldapDateTime' => ['20201002021244Z']]);

        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT+0000', $model->ldapDateTime->toString());
    }

    public function test_windows_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['windowsDateTime' => ['20201002021618.0Z']]);

        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT+0000', $model->windowsDateTime->toString());
    }

    public function test_windows_int_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub())->setRawAttributes(['windowsIntDateTime' => ['132460789290000000']]);

        $this->assertEquals('Fri Oct 02 2020 02:22:09 GMT+0000', $model->windowsIntDateTime->toString());
    }
}

class ModelCastStub extends Model
{
    protected array $casts = [
        'intAttribute' => 'int',
        'integerAttribute' => 'integer',
        'floatAttribute' => 'float',
        'doubleAttribute' => 'float',
        'decimalAttribute' => 'decimal:2',

        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',

        'objectAttribute' => 'object',
        'jsonAttribute' => 'json',
        'collectionAttribute' => 'collection',

        'ldapDateTime' => 'datetime:ldap',
        'windowsDateTime' => 'datetime:windows',
        'windowsIntDateTime' => 'datetime:windows-int',
    ];
}
