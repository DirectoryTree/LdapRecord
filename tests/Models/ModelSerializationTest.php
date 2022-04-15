<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Tests\TestCase;

class ModelSerializationTest extends TestCase
{
    public function testModelWithBinaryGuidAndSidCanBeSerializedAndEncoded()
    {
        $guid = new Guid('2bba564a-4f95-4cb0-97b0-94c0e3458621');
        $sid = new Sid('S-1-5-21-1004336348-1177238915-682003330-512');

        $model = (new Entry())->setRawAttributes([
            'objectguid' => [$guid->getBinary()],
            'objectsid' => [$sid->getBinary()],
        ]);

        $encodedAndSerialized = json_encode(serialize($model));

        $this->assertIsString($encodedAndSerialized);

        $unserializedAndUnencoded = unserialize(json_decode($encodedAndSerialized));

        $this->assertInstanceOf(Entry::class, $unserializedAndUnencoded);

        $this->assertTrue($model->is($unserializedAndUnencoded));

        $this->assertEquals($model->getConvertedGuid(), $unserializedAndUnencoded->getConvertedGuid());
        $this->assertEquals($model->getConvertedSid(), $unserializedAndUnencoded->getConvertedSid());

        $this->assertNotEquals($model->getAttributes()['objectguid'], $unserializedAndUnencoded->getAttributes()['objectguid']);
        $this->assertNotEquals($model->getAttributes()['objectsid'], $unserializedAndUnencoded->getAttributes()['objectsid']);
    }
}
