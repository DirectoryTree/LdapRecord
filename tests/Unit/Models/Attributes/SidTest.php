<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Tests\TestCase;

class SidTest extends TestCase
{
    public function test_identifier_authority_uses_all_six_bytes()
    {
        $binary = hex2bin('0101ffffffffffffffffffff');
        $sid = 'S-1-281474976710655-4294967295';

        $this->assertTrue(Sid::isValid($sid));
        $this->assertSame($sid, (string) new Sid($binary));
        $this->assertSame($binary, (new Sid($sid))->getBinary());
    }

    public function test_binary_subauthorities_must_fit_within_the_available_data()
    {
        $this->expectException(InvalidArgumentException::class);

        new Sid(hex2bin('0101000000000005'));
    }

    public function test_authority_values_cannot_overflow_the_binary_sid()
    {
        $this->assertFalse(Sid::isValid('S-1-281474976710656-0'));
        $this->assertFalse(Sid::isValid('S-1-5-4294967296'));
        $this->assertSame(hex2bin('01010000000000050a000000'), (new Sid('s-1-5-10'))->getBinary());
    }

    public function test_throws_exception_with_empty_sid()
    {
        $this->expectException(InvalidArgumentException::class);

        new Sid('');
    }

    public function test_throws_exception_with_invalid_sid()
    {
        $this->expectException(InvalidArgumentException::class);

        new Sid('invalid');
    }

    public function test_can_be_converted_from_binary()
    {
        $hex = '010500000000000515000000dcf4dc3b833d2b46828ba62800020000';

        $expected = (new Sid(hex2bin($hex)));

        $this->assertEquals(
            'S-1-5-21-1004336348-1177238915-682003330-512',
            $expected->getValue()
        );
    }

    public function test_can_be_converted_from_string()
    {
        $hex = '010500000000000515000000dcf4dc3b833d2b46828ba62800020000';
        $sid = 'S-1-5-21-1004336348-1177238915-682003330-512';

        $expected = (new Sid($sid));

        $this->assertEquals(hex2bin($hex), $expected->getBinary());
    }

    public function test_can_convert_built_in_account_sid_from_binary()
    {
        $hex = '01020000000000052000000020020000';
        $sid = 'S-1-5-32-544';

        $expected = new Sid(hex2bin($hex));

        $this->assertEquals($sid, $expected->getValue());
    }

    public function test_can_convert_builtin_account_sid_from_string()
    {
        $hex = '01020000000000052000000020020000';
        $sid = 'S-1-5-32-544';

        $expected = new Sid($sid);

        $this->assertEquals(hex2bin($hex), $expected->getBinary());
    }

    public function test_can_convert_well_known_nobody_sid_from_binary()
    {
        $hex = '010100000000000000000000';
        $sid = 'S-1-0-0';

        $expected = new Sid(hex2bin($hex));

        $this->assertEquals($sid, $expected->getValue());
    }

    public function test_can_convert_well_known_nobody_sid_from_string()
    {
        $hex = '010100000000000000000000';
        $sid = 'S-1-0-0';

        $expected = new Sid($sid);

        $this->assertEquals(hex2bin($hex), $expected->getBinary());
    }

    public function test_can_convert_well_known_self_sid_from_binary()
    {
        $hex = '01010000000000050a000000';
        $sid = 'S-1-5-10';

        $expected = new Sid(hex2bin($hex));

        $this->assertEquals($sid, $expected->getValue());
    }

    public function test_can_convert_well_known_self_sid_from_string()
    {
        $hex = '01010000000000050a000000';
        $sid = 'S-1-5-10';

        $expected = new Sid($sid);

        $this->assertEquals(hex2bin($hex), $expected->getBinary());
    }
}
