<?php

namespace LdapRecord\Tests\Unit\Models\Attributes\SecurityDescriptor;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Tests\TestCase;
use LogicException;

class AceTest extends TestCase
{
    public function test_allow_factory_encodes_an_unsigned_access_mask()
    {
        $binary = hex2bin('0000140000000080010100000000000100000000');
        $ace = Ace::allow(Sid::EVERYONE, Ace::GENERIC_READ);

        $this->assertSame($binary, $ace->toBinary());
        $this->assertSame(Ace::GENERIC_READ, Ace::fromBinary($binary)->getRights());
        $this->assertSame(Sid::EVERYONE, (string) Ace::fromBinary($binary)->getTrustee());
    }

    public function test_object_entries_preserve_both_guids_unknown_flags_and_application_data()
    {
        $binary = hex2bin(trim(file_get_contents(__DIR__.'/fixtures/object-deny.hex')));
        $ace = Ace::fromBinary($binary);

        $this->assertSame(Ace::ACCESS_DENIED_OBJECT, $ace->getType());
        $this->assertSame(0x12, $ace->getFlags());
        $this->assertSame(0x80000100, $ace->getRights());
        $this->assertSame(0x80000003, $ace->getObjectFlags());
        $this->assertSame('270db4d0-249d-46a7-9cc5-eb695d9af9ac', (string) $ace->getObjectType());
        $this->assertSame(Ace::RESET_PASSWORD, (string) $ace->getInheritedObjectType());
        $this->assertSame(Sid::SELF, (string) $ace->getTrustee());
        $this->assertSame(hex2bin('deadbeef'), $ace->getApplicationData());
        $this->assertTrue($ace->isInherited());
        $this->assertSame($binary, $ace->toBinary());

        $ace->setObjectType(null)->setInheritedObjectType(null);

        $this->assertSame(0x80000000, $ace->getObjectFlags());
        $this->assertNull($ace->getObjectType());
        $this->assertNull($ace->getInheritedObjectType());
        $this->assertSame(hex2bin('deadbeef'), Ace::fromBinary($ace->toBinary())->getApplicationData());
    }

    public function test_setting_object_types_promotes_allow_deny_and_audit_entries()
    {
        $allow = Ace::allow(Sid::SELF, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD);
        $deny = Ace::deny(Sid::EVERYONE, Ace::CONTROL_ACCESS)->setInheritedObjectType(Ace::RESET_PASSWORD);
        $audit = Ace::audit(Sid::SELF, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD)
            ->setFlags(Ace::SUCCESSFUL_ACCESS | Ace::FAILED_ACCESS);

        $this->assertSame(Ace::ACCESS_ALLOWED_OBJECT, $allow->getType());
        $this->assertSame(Ace::ACCESS_DENIED_OBJECT, $deny->getType());
        $this->assertSame(Ace::SYSTEM_AUDIT_OBJECT, $audit->getType());
        $this->assertSame(hex2bin('050028000001000001000000531a72ab2f1ed011981900aa0040529b01010000000000050a000000'), $allow->toBinary());
        $this->assertSame(2, Ace::fromBinary($deny->toBinary())->getObjectFlags());
        $this->assertSame(0xC0, Ace::fromBinary($audit->toBinary())->getFlags());
    }

    public function test_object_layout_without_optional_guids_is_preserved()
    {
        $binary = hex2bin('05001800000100000000000001010000000000050a000000');
        $ace = Ace::fromBinary($binary);

        $this->assertTrue($ace->isObjectAce());
        $this->assertSame(0, $ace->getObjectFlags());
        $this->assertNull($ace->getObjectType());
        $this->assertNull($ace->getInheritedObjectType());
        $this->assertSame($binary, $ace->toBinary());
    }

    public function test_callback_and_unknown_entries_are_preserved_without_interpreting_their_payloads()
    {
        foreach ([0x09, 0x0B, 0x11, 0xFF] as $type) {
            $binary = chr($type).hex2bin('900800deadbeef');
            $ace = Ace::fromBinary($binary);

            $this->assertFalse($ace->isSupported());
            $this->assertNull($ace->getTrustee());
            $this->assertSame($binary, $ace->toBinary());
        }
    }

    public function test_opaque_entries_cannot_be_rewritten_as_an_interpreted_entry()
    {
        $ace = Ace::fromBinary(hex2bin('ff000800deadbeef'));

        $this->expectException(LogicException::class);

        $ace->setType(Ace::ACCESS_ALLOWED);
    }

    public function test_changing_type_cannot_silently_discard_object_data()
    {
        $ace = Ace::deny(Sid::SELF)->setObjectType(Ace::CHANGE_PASSWORD);

        $this->expectException(LogicException::class);

        $ace->setType(Ace::ACCESS_DENIED);
    }

    public function test_object_allow_can_be_changed_to_object_deny()
    {
        $ace = Ace::allow(Sid::EVERYONE, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD);
        $ace->setType(Ace::ACCESS_DENIED_OBJECT);

        $this->assertTrue($ace->isDenyAce());
        $this->assertSame(Ace::CHANGE_PASSWORD, (string) Ace::fromBinary($ace->toBinary())->getObjectType());
    }

    /** @dataProvider invalidEntries */
    public function test_invalid_binary_entries_are_rejected(string $hex)
    {
        $this->expectException(InvalidArgumentException::class);

        Ace::fromBinary(hex2bin($hex));
    }

    public static function invalidEntries(): array
    {
        return [
            'missing header' => ['0000'],
            'wrong size' => ['0000140000000000'],
            'unaligned size' => ['ff00050000'],
            'missing mask' => ['00000400'],
            'missing trustee' => ['0000080000000000'],
            'missing object flags' => ['0500080000000000'],
            'truncated guid' => ['05001000000000000100000000000000'],
            'truncated trustee' => ['00001000000000000101000000000005'],
        ];
    }
}
