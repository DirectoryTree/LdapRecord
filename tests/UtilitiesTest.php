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
}
