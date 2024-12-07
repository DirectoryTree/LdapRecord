<?php

namespace LdapRecord\Tests\Unit\Models;

use DateTime;
use LdapRecord\Models\Model;
use LdapRecord\Query\Collection;
use LdapRecord\Tests\TestCase;
use RuntimeException;

class ModelAttributeCastTest extends TestCase
{
    public function test_boolean_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['TRUE']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['FALSE']]);
        $this->assertFalse($model->boolAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['booleanAttribute' => ['FALSE']]);
        $this->assertFalse($model->booleanAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['booleanAttribute' => ['FALSE']]);
        $this->assertFalse($model->booleanAttribute);

        // Reverse casting

        $model->booleanAttribute = true;
        $this->assertTrue($model->booleanAttribute);
        $this->assertEquals($model->getRawAttribute('booleanAttribute'), ['TRUE']);

        $model->booleanAttribute = 'false';
        $this->assertFalse($model->booleanAttribute);
        $this->assertEquals($model->getRawAttribute('booleanAttribute'), ['FALSE']);

        // Casing differences

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['true']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['false']]);
        $this->assertFalse($model->boolAttribute);

        // Variable differences

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['invalid']]);
        $this->assertTrue($model->boolAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => ['']]);
        $this->assertFalse($model->boolAttribute);

        $model = (new ModelCastStub)->setRawAttributes(['boolAttribute' => []]);
        $this->assertNull($model->boolAttribute);

        $model = (new ModelCastStub);
        $this->assertNull($model->boolAttribute);
    }

    public function test_float_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['floatAttribute' => ['12345.6789']]);

        $this->assertIsFloat($value = $model->floatAttribute);
        $this->assertEquals(12345.6789, $value);

        // Reverse casting

        $model->floatAttribute = 12345.6789;
        $this->assertIsFloat($model->floatAttribute);
        $this->assertEquals($model->getRawAttribute('floatAttribute'), ['12345.6789']);
    }

    public function test_double_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['doubleAttribute' => ['1234.567']]);

        $this->assertIsFloat($value = $model->doubleAttribute);
        $this->assertEquals(1234.567, $value);

        // Reverse casting

        $model->doubleAttribute = 1234.567;
        $this->assertIsFloat($model->doubleAttribute);
        $this->assertEquals($model->getRawAttribute('doubleAttribute'), ['1234.567']);
    }

    public function test_object_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['objectAttribute' => ['{"foo": 1, "bar": "two"}']]);

        $this->assertIsObject($value = $model->objectAttribute);

        $object = (new \stdClass);
        $object->foo = 1;
        $object->bar = 'two';

        $this->assertEquals($object, $value);

        // Reverse casting

        $model->objectAttribute = $object;
        $this->assertIsObject($model->objectAttribute);
        $this->assertEquals($model->getRawAttribute('objectAttribute'), ['{"foo":1,"bar":"two"}']);
    }

    public function test_json_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['jsonAttribute' => ['{"foo": 1, "bar": "two"}']]);

        $this->assertEquals(['foo' => 1, 'bar' => 'two'], $model->jsonAttribute);

        // Reverse casting

        $model->jsonAttribute = ['foo' => 1, 'bar' => 'two'];
        $this->assertEquals(['foo' => 1, 'bar' => 'two'], $model->jsonAttribute);
        $this->assertEquals($model->getRawAttribute('jsonAttribute'), ['{"foo":1,"bar":"two"}']);
    }

    public function test_collection_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['collectionAttribute' => ['foo' => 1, 'bar' => 'two']]);

        $this->assertInstanceOf(Collection::class, $collection = $model->collectionAttribute);

        $this->assertEquals(['foo' => 1, 'bar' => 'two'], $collection->toArray());

        // Reverse casting

        $model->collectionAttribute = new Collection(['foo' => 1, 'bar' => 'two']);
        $this->assertInstanceOf(Collection::class, $model->collectionAttribute);
        $this->assertEquals($model->getRawAttribute('collectionAttribute'), ['{"foo":1,"bar":"two"}']);
    }

    public function test_integer_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes([
            'intAttribute' => ['1234.5678'],
            'integerAttribute' => ['1234.5678'],
        ]);

        $this->assertIsInt($model->intAttribute);
        $this->assertIsInt($model->integerAttribute);

        $this->assertEquals(1234, $model->intAttribute);
        $this->assertEquals(1234, $model->integerAttribute);

        // Reverse casting

        $model->intAttribute = 1234;
        $this->assertEquals(1234, $model->intAttribute);
        $this->assertEquals($model->getRawAttribute('intAttribute'), ['1234']);
    }

    public function test_decimal_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['decimalAttribute' => ['1234.5678']]);

        $this->assertIsString($value = $model->decimalAttribute);

        $this->assertEquals(1234.57, $value);

        // Reverse casting

        $model->decimalAttribute = 1234.57;
        $this->assertIsString($model->decimalAttribute);
        $this->assertEquals($model->getRawAttribute('decimalAttribute'), ['1234.57']);
    }

    public function test_ldap_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['ldapDateTime' => ['20201002021244Z']]);

        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT+0000', $model->ldapDateTime->toString());

        // Reverse casting

        $model->ldapDateTime = new DateTime('2020-10-02 02:12:44');
        $this->assertEquals($model->getRawAttribute('ldapDateTime'), ['20201002021244Z']);
    }

    public function test_windows_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['windowsDateTime' => ['20201002021618.0Z']]);

        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT+0000', $model->windowsDateTime->toString());

        // Reverse casting

        $model->windowsDateTime = new DateTime('2020-10-02 02:16:18');
        $this->assertEquals($model->getRawAttribute('windowsDateTime'), ['20201002021618.0Z']);
    }

    public function test_windows_int_datetime_attributes_are_casted()
    {
        $model = (new ModelCastStub)->setRawAttributes(['windowsIntDateTime' => ['132460789290000000']]);

        $this->assertEquals('Fri Oct 02 2020 02:22:09 GMT+0000', $model->windowsIntDateTime->toString());

        // Reverse casting

        $model->windowsIntDateTime = new DateTime('2020-10-02 02:22:09');
        $this->assertEquals($model->getRawAttribute('windowsIntDateTime'), ['132460789290000000']);
    }

    public function test_get_dates()
    {
        $dates = (new ModelCastStub)->getDates();

        $this->assertEquals([
            'createtimestamp' => 'ldap',
            'modifytimestamp' => 'ldap',
            'ldapdatetime' => 'ldap',
            'windowsdatetime' => 'windows',
            'windowsintdatetime' => 'windows-int',
        ], $dates);
    }

    public function test_get_dates_throws_exception_when_no_format_is_provided()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Invalid date cast [datetime]. A date cast must be in the format 'datetime:format'.");

        (new ModelDateCastWithNoFormatStub)->getDates();
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

class ModelDateCastWithNoFormatStub extends Model
{
    protected array $casts = [
        'attribute' => 'datetime',
    ];
}
