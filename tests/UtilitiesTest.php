<?php

namespace LdapRecord\Tests;

use LdapRecord\Utilities;

class UtilitiesTest extends TestCase
{
    public function test_explode_dn()
    {
        $dn = 'cn=Testing,ou=Folder,dc=corp,dc=org';

        $split = Utilities::explodeDn($dn);

        $expected = ['Testing', 'Folder', 'corp', 'org'];

        $this->assertEquals($expected, $split);
    }

    public function test_unescape()
    {
        $unescaped = '!@#$%^&*()';

        $escaped = ldap_escape($unescaped);

        $this->assertEquals($unescaped, Utilities::unescape($escaped));
    }

    public function test_is_valid_sid()
    {
        $this->assertTrue(Utilities::isValidSid('S-1-5-21-3623811015-3361044348-30300820-1013'));
        $this->assertTrue(Utilities::isValidSid('S-1-5-21-362381101-336104434-3030082-101'));
        $this->assertTrue(Utilities::isValidSid('S-1-5-21-362381101-336104434'));
        $this->assertTrue(Utilities::isValidSid('S-1-5-21-362381101'));
        $this->assertTrue(Utilities::isValidSid('S-1-5-21'));
        $this->assertTrue(Utilities::isValidSid('S-1-5'));

        $this->assertFalse(Utilities::isValidSid('Invalid SID'));
        $this->assertFalse(Utilities::isValidSid('S-1'));
        $this->assertFalse(Utilities::isValidSid(''));
    }

    public function test_is_valid_guid()
    {
        $this->assertTrue(Utilities::isValidGuid('59e5e143-a50e-41a9-bf2b-badee699a577'));
        $this->assertTrue(Utilities::isValidGuid('8be90b30-0bbb-4638-b468-7aaeb32c74f9'));
        $this->assertTrue(Utilities::isValidGuid('17bab266-05ac-4e30-9fad-1c7093e4dd83'));

        $this->assertFalse(Utilities::isValidGuid('Invalid GUID'));
        $this->assertFalse(Utilities::isValidGuid('17bab266-05ac-4e30-9fad'));
        $this->assertFalse(Utilities::isValidGuid(''));
    }

    public function test_string_guid_to_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals('\d0\b4\0d\27\9d\24\a7\46\9c\c5\eb\69\5d\9a\f9\ac', Utilities::stringGuidToHex($guid));
    }
}
