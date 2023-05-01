<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Tests\TestCase;

class GuidTest extends TestCase
{
    public function test_throws_exception_with_empty_guid()
    {
        $this->expectException(InvalidArgumentException::class);

        new Guid('');
    }

    public function test_get_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals(
            'd0b40d279d24a7469cc5eb695d9af9ac',
            (new Guid($guid))->getHex()
        );
    }

    public function test_get_encoded_hex()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals(
            '\d0\b4\0d\27\9d\24\a7\46\9c\c5\eb\69\5d\9a\f9\ac',
            (new Guid($guid))->getEncodedHex()
        );
    }

    public function test_get_binary()
    {
        $guid = '270db4d0-249d-46a7-9cc5-eb695d9af9ac';

        $this->assertEquals(
            'd0b40d279d24a7469cc5eb695d9af9ac',
            bin2hex((new Guid($guid))->getBinary())
        );
    }

    public function test_get_value()
    {
        $hex = 'd0b40d279d24a7469cc5eb695d9af9ac';

        $this->assertEquals(
            '270db4d0-249d-46a7-9cc5-eb695d9af9ac',
            (new Guid(hex2bin($hex)))->getValue()
        );
    }
}
