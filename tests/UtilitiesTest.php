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

    public function test_string_guid_to_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals('\d0\b4\0d\27\9d\24\a7\46\9c\c5\eb\69\5d\9a\f9\ac', Utilities::stringGuidToHex($guid));
    }
}
