<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;

class ModelAttributeCastTest extends TestCase
{
    public function test_boolean_attributes_are_casted()
    {
        $model = new ModelCastStub(['boolAttribute' => 'TRUE']);
        $this->assertTrue($model->boolAttribute);

        $model = new ModelCastStub(['boolAttribute' => 'FALSE']);
        $this->assertFalse($model->boolAttribute);

        $model = new ModelCastStub(['booleanAttribute' => 'FALSE']);
        $this->assertFalse($model->booleanAttribute);

        $model = new ModelCastStub(['booleanAttribute' => 'FALSE']);
        $this->assertFalse($model->booleanAttribute);

        // Casing differences

        $model = new ModelCastStub(['boolAttribute' => 'true']);
        $this->assertTrue($model->boolAttribute);

        $model = new ModelCastStub(['boolAttribute' => 'false']);
        $this->assertFalse($model->boolAttribute);

        // Variable differences

        $model = new ModelCastStub(['boolAttribute' => 'invalid']);
        $this->assertTrue($model->boolAttribute);

        $model = new ModelCastStub(['boolAttribute' => '']);
        $this->assertFalse($model->boolAttribute);
    }

    public function test_float_attributes_are_casted()
    {
        $model = new ModelCastStub(['floatAttribute' => '12345.6789']);

        $this->assertInternalType('float', $value = $model->floatAttribute);
        $this->assertEquals(12345.6789, $value);
    }

    public function test_double_attributes_are_casted()
    {
        $model = new ModelCastStub(['doubleAttribute' => '1234.567']);

        $this->assertInternalType('double', $value = $model->doubleAttribute);
        $this->assertEquals(1234.567, $value);
    }

    public function test_object_attributes_are_casted()
    {
        $model = new ModelCastStub(['objectAttribute' => ['foo' => 1, 'bar' => 'two']]);

        $this->assertEquals(['{"foo":1,"bar":"two"}'], $model->getAttributes()['objectattribute']);

        $this->assertInternalType('object', $value = $model->objectAttribute);

        $object = (new \stdClass);
        $object->foo = 1;
        $object->bar = 'two';

        $this->assertEquals($object, $value);
    }

    public function test_integer_attributes_are_casted()
    {
        $model = new ModelCastStub([
            'intAttribute' => '1234.5678',
            'integerAttribute' => '1234.5678',
        ]);

        $this->assertInternalType('int', $model->intAttribute);
        $this->assertInternalType('int', $model->integerAttribute);

        $this->assertEquals(1234, $model->intAttribute);
        $this->assertEquals(1234, $model->integerAttribute);
    }

    public function test_decimal_attributes_are_casted()
    {
        $model = new ModelCastStub(['decimalAttribute' => '1234.5678']);

        $this->assertInternalType('string', $value = $model->decimalAttribute);

        $this->assertEquals(1234.568, $value);
    }

    public function test_ldap_datetime_attributes_are_casted()
    {
        $model = new ModelCastStub(['ldapDateTime' => '20201002021244Z']);

        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT+0000', $model->ldapDateTime->toString());
    }
}

class ModelCastStub extends Model
{
    protected $casts = [
        'intAttribute' => 'int',
        'integerAttribute' => 'integer',

        'boolAttribute' => 'bool',
        'booleanAttribute' => 'boolean',

        'floatAttribute' => 'float',
        'doubleAttribute' => 'float',

        'objectAttribute' => 'object',

        'decimalAttribute' => 'decimal:3',

        'ldapDateTime' => 'datetime:ldap',
        'windowsDateTime' => 'datetime:windows',
        'windowsIntDateTime' => 'datetime:windows-int',
    ];
}