<?php

namespace LdapRecord\Tests\Unit\Models\Attributes\SecurityDescriptor;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\SecurityDescriptor\Acl;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Tests\TestCase;
use LogicException;

class AclTest extends TestCase
{
    public function test_lists_expose_entries_through_a_collection_and_preserve_order()
    {
        $allow = Ace::allow(Sid::EVERYONE, Ace::GENERIC_READ);
        $deny = Ace::deny(Sid::SELF);
        $acl = (new Acl)->addAce($allow, $deny);

        $this->assertSame([$allow, $deny], $acl->getAces()->all());
        $this->assertSame(hex2bin('02003000020000000000140000000080010100000000000100000000010014000000000001010000000000050a000000'), $acl->toBinary());
        $this->assertSame(2, $acl->getRevision());
        $this->assertSame([$deny], $acl->removeAce($allow)->getAces()->all());
    }

    public function test_canonicalization_is_explicit_and_stable()
    {
        $allow = Ace::allow(Sid::SELF);
        $inheritedAllow = Ace::allow(Sid::SELF)->setFlags(Ace::INHERITED);
        $objectDeny = Ace::deny(Sid::SELF)->setObjectType(Ace::CHANGE_PASSWORD);
        $deny = Ace::deny(Sid::EVERYONE);
        $objectAllow = Ace::allow(Sid::EVERYONE)->setObjectType(Ace::CHANGE_PASSWORD);
        $inheritedDeny = Ace::deny(Sid::SELF)->setFlags(Ace::INHERITED);
        $acl = (new Acl)->addAce($allow, $inheritedAllow, $objectDeny, $deny, $objectAllow, $inheritedDeny);

        $acl->toBinary();

        $this->assertSame([$allow, $inheritedAllow, $objectDeny, $deny, $objectAllow, $inheritedDeny], $acl->getAces()->all());
        $this->assertSame([$objectDeny, $deny, $allow, $objectAllow, $inheritedAllow, $inheritedDeny], $acl->canonicalize()->getAces()->all());
        $this->assertSame(4, $acl->getRevision());
    }

    public function test_unknown_entries_reserved_bytes_and_padding_are_retained()
    {
        $binary = hex2bin('04aa14000100efbeff100800deadbeef12345678');
        $acl = new Acl($binary);

        $this->assertSame(1, $acl->getAces()->count());
        $this->assertSame($binary, $acl->toBinary());
    }

    public function test_canonicalizing_unknown_entries_fails_without_changing_order()
    {
        $unknown = Ace::fromBinary(hex2bin('ff000800deadbeef'));
        $acl = (new Acl)->addAce(Ace::allow(Sid::SELF), $unknown);

        $this->expectException(LogicException::class);

        $acl->canonicalize();
    }

    /** @dataProvider invalidLists */
    public function test_invalid_lists_are_rejected(string $hex)
    {
        $this->expectException(InvalidArgumentException::class);

        new Acl(hex2bin($hex));
    }

    public static function invalidLists(): array
    {
        return [
            'missing header' => ['0200'],
            'unsupported revision' => ['0100080000000000'],
            'wrong size' => ['02000c0000000000'],
            'wrong count' => ['0200080001000000'],
            'entry exceeds size' => ['02000c000100000000001400'],
            'empty entry' => ['02000c000100000000000000'],
        ];
    }
}
