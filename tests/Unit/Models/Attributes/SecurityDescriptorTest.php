<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\SecurityDescriptor;
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\SecurityDescriptor\Acl;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Tests\TestCase;

class SecurityDescriptorTest extends TestCase
{
    public function test_a_large_active_directory_descriptor_preserves_all_entries()
    {
        $binary = hex2bin(trim(file_get_contents(__DIR__.'/SecurityDescriptor/fixtures/active-directory.hex')));
        $expected = hex2bin(trim(file_get_contents(__DIR__.'/SecurityDescriptor/fixtures/active-directory-normalized.hex')));
        $descriptor = new SecurityDescriptor($binary);

        $this->assertCount(85, $descriptor->getDacl()->getAces());
        $this->assertCount(3, $descriptor->getSacl()->getAces());
        $this->assertSame('S-1-5-21-1263317781-1938881490-3107577794-512', (string) $descriptor->getOwner());
        $this->assertSame('S-1-5-21-1263317781-1938881490-3107577794-512', (string) $descriptor->getGroup());
        $this->assertSame(0x9C14, $descriptor->getControlFlags());
        $this->assertSame($expected, $descriptor->toBinary());
    }

    public function test_a_known_descriptor_can_be_read_and_serialized()
    {
        $binary = hex2bin(trim(file_get_contents(__DIR__.'/SecurityDescriptor/fixtures/self-owned.hex')));
        $descriptor = new SecurityDescriptor($binary);

        $this->assertSame(1, $descriptor->getRevision());
        $this->assertSame(0, $descriptor->getResourceManagerControl());
        $this->assertSame(0x8004, $descriptor->getControlFlags());
        $this->assertSame(Sid::SELF, (string) $descriptor->getOwner());
        $this->assertSame(Sid::SELF, (string) $descriptor->getGroup());
        $this->assertTrue($descriptor->hasDacl());
        $this->assertFalse($descriptor->hasSacl());
        $this->assertNull($descriptor->getSacl());
        $this->assertSame(1, $descriptor->getDacl()->getAces()->count());
        $this->assertSame(Ace::READ_CONTROL | Ace::CREATE_CHILD, $descriptor->getDacl()->getAces()->first()->getRights());
        $this->assertSame($binary, $descriptor->toBinary());
    }

    public function test_absent_null_and_empty_lists_remain_distinct()
    {
        $absent = new SecurityDescriptor(hex2bin('0100008000000000000000000000000000000000'));
        $null = new SecurityDescriptor(hex2bin('0100148000000000000000000000000000000000'));
        $empty = new SecurityDescriptor(hex2bin('01000480000000000000000000000000140000000200080000000000'));

        $this->assertFalse($absent->hasDacl());
        $this->assertNull($absent->getDacl());
        $this->assertTrue($null->hasDacl());
        $this->assertTrue($null->hasSacl());
        $this->assertNull($null->getDacl());
        $this->assertNull($null->getSacl());
        $this->assertTrue($empty->hasDacl());
        $this->assertCount(0, $empty->getDacl()->getAces());
        $this->assertSame(hex2bin('0100008000000000000000000000000000000000'), $absent->toBinary());
        $this->assertSame(hex2bin('0100148000000000000000000000000000000000'), $null->toBinary());
        $this->assertSame(hex2bin('01000480000000000000000000000000140000000200080000000000'), $empty->toBinary());
        $this->assertSame($null->toBinary(), (new SecurityDescriptor)->setDacl(null)->setSacl(null)->toBinary());
        $this->assertSame($absent->toBinary(), $null->unsetDacl()->unsetSacl()->toBinary());
    }

    public function test_nonstandard_component_order_and_shared_sid_offsets_can_be_read()
    {
        $binary = hex2bin('010004801c0000001c0000000000000014000000020008000000000001010000000000050a000000');
        $descriptor = new SecurityDescriptor($binary);

        $this->assertSame(Sid::SELF, (string) $descriptor->getOwner());
        $this->assertSame(Sid::SELF, (string) $descriptor->getGroup());
        $this->assertCount(0, $descriptor->getDacl()->getAces());
        $this->assertSame(hex2bin('010004801400000020000000000000002c00000001010000000000050a00000001010000000000050a0000000200080000000000'), $descriptor->toBinary());
    }

    public function test_resource_manager_byte_and_flags_are_preserved()
    {
        $binary = hex2bin('01abe0c000000000000000000000000000000000');
        $descriptor = new SecurityDescriptor($binary);

        $this->assertSame(0xAB, $descriptor->getResourceManagerControl());
        $this->assertSame(0xC0E0, $descriptor->getControlFlags());
        $this->assertSame($binary, $descriptor->toBinary());
    }

    public function test_audit_lists_and_opaque_entries_are_preserved()
    {
        $binary = hex2bin('010014800000000000000000140000003000000002001c000100000002c014000000008001010000000000050a0000000400100001000000ff900800deadbeef');
        $descriptor = new SecurityDescriptor($binary);

        $this->assertSame(Ace::SYSTEM_AUDIT, $descriptor->getSacl()->getAces()->first()->getType());
        $this->assertSame(Ace::GENERIC_READ, $descriptor->getSacl()->getAces()->first()->getRights());
        $this->assertFalse($descriptor->getDacl()->getAces()->first()->isSupported());
        $this->assertSame($binary, $descriptor->toBinary());
    }

    public function test_merging_selected_sections_preserves_other_sections_and_control_bits()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl(new Acl)->setSacl((new Acl)->addAce(Ace::audit(Sid::SELF)))
            ->setResourceManagerControl(0xAB)->setControlFlags(0xCC15);
        $replacement = (new SecurityDescriptor)->setOwner(Sid::EVERYONE)->setGroup(Sid::EVERYONE)
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::EVERYONE)))
            ->setControlFlags(0x9004);

        $original->merge($replacement, SecurityDescriptor::DACL_SECURITY_INFORMATION);

        $this->assertSame(Sid::SELF, (string) $original->getOwner());
        $this->assertSame(Sid::SELF, (string) $original->getGroup());
        $this->assertSame(Ace::SYSTEM_AUDIT, $original->getSacl()->getAces()->first()->getType());
        $this->assertSame(Ace::ACCESS_DENIED, $original->getDacl()->getAces()->first()->getType());
        $this->assertSame(0xAB, $original->getResourceManagerControl());
        $this->assertSame(0xD815, $original->getControlFlags());

        $original->merge($replacement, SecurityDescriptor::OWNER_SECURITY_INFORMATION | SecurityDescriptor::GROUP_SECURITY_INFORMATION | SecurityDescriptor::SACL_SECURITY_INFORMATION);

        $this->assertSame(Sid::EVERYONE, (string) $original->getOwner());
        $this->assertSame(Sid::EVERYONE, (string) $original->getGroup());
        $this->assertFalse($original->hasSacl());
        $this->assertNull($original->getSacl());
        $this->assertSame(0xD004, $original->getControlFlags());
    }

    public function test_changed_parts_include_section_flags_without_mutating_either_descriptor()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl(new Acl)->setSacl(new Acl)->setControlFlags(0xC014);
        $replacement = (new SecurityDescriptor($original->toBinary()))
            ->setControlFlags($original->getControlFlags() | SecurityDescriptor::OWNER_DEFAULTED | SecurityDescriptor::SACL_PROTECTED);
        $originalBinary = $original->toBinary();
        $replacementBinary = $replacement->toBinary();

        $this->assertSame(0, $original->getChangedParts(new SecurityDescriptor($originalBinary)));
        $this->assertSame(
            SecurityDescriptor::OWNER_SECURITY_INFORMATION | SecurityDescriptor::SACL_SECURITY_INFORMATION,
            $replacement->getChangedParts($original)
        );
        $this->assertSame($originalBinary, $original->toBinary());
        $this->assertSame($replacementBinary, $replacement->toBinary());
    }

    public function test_changed_parts_distinguish_absent_null_and_empty_acls()
    {
        $absent = new SecurityDescriptor;
        $null = (new SecurityDescriptor)->setDacl(null)->setSacl(null);
        $empty = (new SecurityDescriptor)->setDacl(new Acl)->setSacl(new Acl);

        $this->assertSame(12, $null->getChangedParts($absent));
        $this->assertSame(12, $empty->getChangedParts($null));
        $this->assertSame(12, $absent->getChangedParts($empty));
    }

    public function test_changed_parts_detect_primary_group_changes()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)->setDacl(new Acl);
        $replacement = (new SecurityDescriptor($original->toBinary()))->setGroup(Sid::EVERYONE);

        $this->assertSame(SecurityDescriptor::GROUP_SECURITY_INFORMATION, $replacement->getChangedParts($original));
    }

    public function test_setting_all_sections_uses_expected_byte_offsets()
    {
        $descriptor = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::EVERYONE)
            ->setSacl((new Acl)->addAce(Ace::audit(Sid::SELF, Ace::GENERIC_READ)->setFlags(Ace::SUCCESSFUL_ACCESS)))
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::EVERYONE, Ace::CONTROL_ACCESS)));

        $this->assertSame(hex2bin('0100148014000000200000002c0000004800000001010000000000050a00000001010000000000010000000002001c0001000000024014000000008001010000000000050a00000002001c00010000000100140000010000010100000000000100000000'), $descriptor->toBinary());
    }

    public function test_password_restrictions_preserve_other_permissions_and_are_repeatable()
    {
        $everyone = Ace::allow(Sid::EVERYONE, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD);
        $other = Ace::allow(Sid::SELF, Ace::READ_PROPERTY | Ace::WRITE_PROPERTY);
        $inherited = Ace::allow(Sid::EVERYONE)->setFlags(Ace::INHERITED);
        $descriptor = (new SecurityDescriptor)->setDacl((new Acl)->addAce($everyone, $other, $inherited));
        $otherBinary = $other->toBinary();
        $inheritedBinary = $inherited->toBinary();

        for ($i = 0; $i < 2; $i++) {
            $dacl = $descriptor->getDacl();

            foreach ([Sid::EVERYONE, Sid::SELF] as $trustee) {
                $entry = $dacl->getAces()->first(fn (Ace $ace) => $ace->isSupported()
                    && ($ace->isAllowAce() || $ace->isDenyAce())
                    && ! $ace->isInherited()
                    && (string) $ace->getTrustee() === $trustee
                    && (string) $ace->getObjectType() === Ace::CHANGE_PASSWORD
                    && $ace->getInheritedObjectType() === null
                    && $ace->getRights() === Ace::CONTROL_ACCESS);

                if ($entry) {
                    $entry->setType(Ace::ACCESS_DENIED_OBJECT);
                } else {
                    $dacl->addAce(Ace::deny($trustee, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD));
                }
            }

            $dacl->canonicalize();
            $descriptor = new SecurityDescriptor($descriptor->toBinary());
        }

        $aces = $descriptor->getDacl()->getAces();
        $this->assertCount(4, $aces);
        $this->assertSame(Ace::ACCESS_DENIED_OBJECT, $aces[0]->getType());
        $this->assertSame(Sid::EVERYONE, (string) $aces[0]->getTrustee());
        $this->assertSame(Ace::ACCESS_DENIED_OBJECT, $aces[1]->getType());
        $this->assertSame(Sid::SELF, (string) $aces[1]->getTrustee());
        $this->assertSame($otherBinary, $aces[2]->toBinary());
        $this->assertSame($inheritedBinary, $aces[3]->toBinary());
    }

    /** @dataProvider invalidDescriptors */
    public function test_malformed_descriptors_are_rejected(string $hex)
    {
        $this->expectException(InvalidArgumentException::class);

        new SecurityDescriptor(hex2bin($hex));
    }

    public static function invalidDescriptors(): array
    {
        return [
            'empty' => [''],
            'truncated header' => ['01000080'],
            'wrong revision' => ['0200008000000000000000000000000000000000'],
            'absolute descriptor' => ['0100000000000000000000000000000000000000'],
            'offset within header' => ['0100008004000000000000000000000000000000'],
            'offset beyond data' => ['01000080ffffff7f000000000000000000000000'],
            'unaligned offset' => ['01000080150000000000000000000000000000000001010000000000050a000000'],
            'truncated owner' => ['01000080140000000000000000000000000000000101000000000005'],
            'truncated acl' => ['01000480000000000000000000000000140000000200300001000000'],
            'acl without present flag' => ['01000080000000000000000000000000140000000200080000000000'],
        ];
    }
}
