<?php

namespace LdapRecord\Tests\Models\Attributes;

use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Tests\TestCase;

class GuidTest extends TestCase
{
    public function test_can_convert_guid_from_string_to_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $expected = new Guid($guid);

        $this->assertEquals(
            'd0b40d279d24a7469cc5eb695d9af9ac',
            bin2hex($expected->getBinary())
        );
    }

    public function test_can_convert_guid_from_string_to_encoded_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals('\d0\b4\0d\27\9d\24\a7\46\9c\c5\eb\69\5d\9a\f9\ac', (new Guid($guid))->getEncodedHex());
    }

    public function test_can_convert_guid_from_binary_to_string()
    {
        $hex = 'd0b40d279d24a7469cc5eb695d9af9ac';

        $expected = new Guid(hex2bin($hex));

        $this->assertEquals(
            '270db4d0-249d-46a7-9cc5-eb695d9af9ac',
            $expected->getValue()
        );
    }

    public function test_is_valid()
    {
        $this->assertTrue(Guid::isValid('59e5e143-a50e-41a9-bf2b-badee699a577'));
        $this->assertTrue(Guid::isValid('8be90b30-0bbb-4638-b468-7aaeb32c74f9'));
        $this->assertTrue(Guid::isValid('17bab266-05ac-4e30-9fad-1c7093e4dd83'));

        $this->assertFalse(Guid::isValid('Invalid GUID'));
        $this->assertFalse(Guid::isValid('17bab266-05ac-4e30-9fad'));
        $this->assertFalse(Guid::isValid(''));
    }
}
