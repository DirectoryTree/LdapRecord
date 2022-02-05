<?php

namespace LdapRecord\Tests\Models\Attributes;

use LdapRecord\Models\Attributes\Hex;
use LdapRecord\Tests\TestCase;

class HexTest extends TestCase
{
    public function test_unescape()
    {
        $unescaped = '!@#$%^&*()';

        $escaped = ldap_escape($unescaped);

        $this->assertEquals($unescaped, Hex::unescape($escaped));
    }
}
