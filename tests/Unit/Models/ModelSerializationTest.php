<?php

namespace LdapRecord\Tests\Unit\Models;

use DateTime;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Tests\TestCase;

class ModelSerializationTest extends TestCase
{
    public function test_model_with_timestamps_can_be_serialized_and_encoded()
    {
        $whenchanged = (new Timestamp('windows'))->fromDateTime(new DateTime);
        $lastlogon = (new Timestamp('windows-int'))->fromDateTime(new DateTime);

        $model = (new User)->setRawAttributes([
            'cn' => 'René',
            'dn' => 'cn=René',
            'whenchanged' => [(string) $whenchanged],
            'lastlogon' => [(string) $lastlogon],
        ]);

        $encodedAndSerialized = json_encode(serialize(clone $model));

        $this->assertIsString($encodedAndSerialized);

        $unserializedAndUnencoded = unserialize(json_decode($encodedAndSerialized));

        $this->assertInstanceOf(User::class, $unserializedAndUnencoded);

        $this->assertTrue($model->is($unserializedAndUnencoded));

        $this->assertEquals($model->getOriginal()['cn'], $unserializedAndUnencoded->getOriginal()['cn']);

        $this->assertEquals($model->getOriginal()['lastlogon'], $unserializedAndUnencoded->getOriginal()['lastlogon']);
        $this->assertEquals($model->getOriginal()['whenchanged'], $unserializedAndUnencoded->getOriginal()['whenchanged']);

        $this->assertEquals($model->getAttributes()['lastlogon'], $unserializedAndUnencoded->getAttributes()['lastlogon']);
        $this->assertEquals($model->getAttributes()['whenchanged'], $unserializedAndUnencoded->getAttributes()['whenchanged']);
    }

    public function test_model_with_binary_guid_and_sid_can_be_serialized_and_encoded()
    {
        $guid = new Guid('2bba564a-4f95-4cb0-97b0-94c0e3458621');
        $sid = new Sid('S-1-5-21-1004336348-1177238915-682003330-512');

        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=Foo Bar',
            'objectguid' => [$guid->getBinary()],
            'objectsid' => [$sid->getBinary()],
        ]);

        $encodedAndSerialized = json_encode(serialize(clone $model));

        $this->assertIsString($encodedAndSerialized);

        $unserializedAndUnencoded = unserialize(json_decode($encodedAndSerialized));

        $this->assertInstanceOf(Entry::class, $unserializedAndUnencoded);

        $this->assertTrue($model->is($unserializedAndUnencoded));

        $this->assertEquals($model->getConvertedSid(), $unserializedAndUnencoded->getConvertedSid());
        $this->assertEquals($model->getConvertedGuid(), $unserializedAndUnencoded->getConvertedGuid());

        $this->assertEquals($model->getOriginal()['objectsid'], $unserializedAndUnencoded->getOriginal()['objectsid']);
        $this->assertEquals($model->getOriginal()['objectguid'], $unserializedAndUnencoded->getOriginal()['objectguid']);

        $this->assertEquals($model->getAttributes()['objectsid'], $unserializedAndUnencoded->getAttributes()['objectsid']);
        $this->assertEquals($model->getAttributes()['objectguid'], $unserializedAndUnencoded->getAttributes()['objectguid']);
    }
}
