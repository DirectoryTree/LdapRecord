<?php

namespace LdapRecord\Tests\Unit\Models\ActiveDirectory;

use InvalidArgumentException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Attributes\SecurityDescriptor;
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\SecurityDescriptor\Acl;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Models\Events\Saved;
use LdapRecord\Models\Events\Saving;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Updating;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class SecurityDescriptorTest extends TestCase
{
    public function test_descriptor_access_is_explicit_and_returns_a_fresh_instance()
    {
        $binary = hex2bin('01000480000000000000000000000000140000000200080000000000');
        $entry = (new Entry)->setRawAttributes(['ntsecuritydescriptor' => [$binary]]);

        $this->assertNull((new Entry)->securityDescriptor());
        $this->assertSame([$binary], $entry->getAttribute('ntsecuritydescriptor'));
        $this->assertNotSame($entry->securityDescriptor(), $entry->securityDescriptor());

        $descriptor = $entry->securityDescriptor();
        $descriptor->getDacl()->addAce(Ace::deny(Sid::SELF));

        $this->assertSame([$binary], $entry->getAttribute('ntsecuritydescriptor'));
        $entry->ntSecurityDescriptor = $descriptor;
        $this->assertSame([$descriptor->toBinary()], $entry->getAttribute('ntsecuritydescriptor'));
        $this->assertTrue($entry->isDirty('ntsecuritydescriptor'));

        $entry->setFirstAttribute('ntSecurityDescriptor', $binary);
        $this->assertSame([$binary], $entry->getAttribute('ntsecuritydescriptor'));
        $entry->ntSecurityDescriptor = null;
        $this->assertNull($entry->securityDescriptor());
    }

    public function test_raw_string_and_array_assignment_are_preserved()
    {
        $binary = hex2bin('0100008000000000000000000000000000000000');
        $entry = new Entry(['ntSecurityDescriptor' => $binary]);

        $this->assertSame([$binary], $entry->getAttribute('ntsecuritydescriptor'));
        $entry->ntSecurityDescriptor = [$binary];
        $this->assertSame([$binary], $entry->getAttribute('ntsecuritydescriptor'));
    }

    public function test_descriptors_are_base64_encoded_for_json_and_restored_for_serialization()
    {
        $binary = hex2bin('01ab00c000000000000000000000000000000000');
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$binary]]);

        $this->assertSame([base64_encode($binary)], $entry->toArray()['ntsecuritydescriptor']);
        $this->assertSame([base64_encode($binary)], json_decode($entry->toJson(), true)['ntsecuritydescriptor']);

        $entry->ntSecurityDescriptor = (new SecurityDescriptor($binary))->setDacl(new Acl);
        $restored = unserialize(json_decode(json_encode(serialize($entry))));

        $this->assertSame($entry->getAttributes(), $restored->getAttributes());
        $this->assertSame($entry->getOriginal(), $restored->getOriginal());
        $this->assertTrue($restored->isDirty('ntsecuritydescriptor'));
    }

    public function test_saving_permissions_preserves_other_sections_pending_attributes_controls_and_events()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl(new Acl)->setSacl((new Acl)->addAce(Ace::audit(Sid::SELF)))
            ->setControlFlags(0xA014);
        $replacement = (new SecurityDescriptor)->setOwner(Sid::EVERYONE)->setGroup(Sid::EVERYONE)
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::EVERYONE, Ace::CONTROL_ACCESS)))
            ->setControlFlags(0x9004);
        $previous = [
            ['oid' => '1.2.3', 'isCritical' => false, 'value' => null],
            ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020107')],
        ];
        $controls = [
            $previous[0],
            ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')],
        ];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn($previous),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modReplace')->once()->with('cn=Jane', ['ntsecuritydescriptor' => [$replacement->toBinary()]])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $previous)->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $dispatcher = m::mock(\LdapRecord\Events\DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updated::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class)->ordered();
        Container::getInstance()->setDispatcher($dispatcher);

        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'cn' => ['Jane'], 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->cn = 'Janet';
        $entry->saveSecurityDescriptor($replacement);

        $saved = $entry->securityDescriptor();
        $this->assertSame(Sid::SELF, (string) $saved->getOwner());
        $this->assertSame(Sid::SELF, (string) $saved->getGroup());
        $this->assertSame(Ace::SYSTEM_AUDIT, $saved->getSacl()->getAces()->first()->getType());
        $this->assertSame(Ace::ACCESS_DENIED, $saved->getDacl()->getAces()->first()->getType());
        $this->assertSame(0xB014, $saved->getControlFlags());
        $this->assertSame(['Janet'], $entry->getAttribute('cn'));
        $this->assertSame(['Jane'], $entry->getOriginal()['cn']);
        $this->assertTrue($entry->isDirty('cn'));
        $this->assertFalse($entry->isDirty('ntsecuritydescriptor'));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_owner_and_audit_writes_do_not_replace_the_loaded_dacl_or_group()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl((new Acl)->addAce(Ace::allow(Sid::SELF)))
            ->setSacl(new Acl)->setControlFlags(0x9014);
        $replacement = (new SecurityDescriptor)->setOwner(Sid::EVERYONE)->setGroup(Sid::EVERYONE)
            ->setSacl((new Acl)->addAce(Ace::audit(Sid::EVERYONE, Ace::GENERIC_READ)->setFlags(Ace::SUCCESSFUL_ACCESS)))
            ->setControlFlags(0xA011);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020109')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modReplace')->once()->with('cn=Jane', ['ntsecuritydescriptor' => [$replacement->toBinary()]])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);

        $entry->saveSecurityDescriptor($replacement, SecurityDescriptor::OWNER_SECURITY_INFORMATION | SecurityDescriptor::SACL_SECURITY_INFORMATION);

        $saved = $entry->securityDescriptor();
        $this->assertSame(Sid::EVERYONE, (string) $saved->getOwner());
        $this->assertSame(Sid::SELF, (string) $saved->getGroup());
        $this->assertSame($original->getDacl()->toBinary(), $saved->getDacl()->toBinary());
        $this->assertSame($replacement->getSacl()->toBinary(), $saved->getSacl()->toBinary());
        $this->assertSame(0xB015, $saved->getControlFlags());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_failed_writes_restore_controls_without_changing_local_attributes_or_firing_success_events()
    {
        $previous = [['oid' => '1.2.3', 'isCritical' => false, 'value' => null]];
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $controls = [$previous[0], ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn($previous),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modReplace')->once()->with('cn=Jane', ['ntsecuritydescriptor' => [$descriptor->toBinary()]])->andThrow(new LdapRecordException('Insufficient access')),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $previous)->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $dispatcher = m::mock(\LdapRecord\Events\DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class)->ordered();
        Container::getInstance()->setDispatcher($dispatcher);
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'cn' => ['Jane']]);
        $entry->cn = 'Janet';
        $attributes = $entry->getAttributes();
        $original = $entry->getOriginal();

        try {
            $entry->saveSecurityDescriptor($descriptor);
            $this->fail('The write should fail.');
        } catch (LdapRecordException $e) {
            $this->assertSame('Insufficient access', $e->getMessage());
        }

        $this->assertSame($attributes, $entry->getAttributes());
        $this->assertSame($original, $entry->getOriginal());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_saving_a_null_dacl_preserves_its_present_flag()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(null);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modReplace')->once()->with('cn=Jane', ['ntsecuritydescriptor' => [$descriptor->toBinary()]])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);

        $entry->saveSecurityDescriptor($descriptor);

        $this->assertTrue($entry->securityDescriptor()->hasDacl());
        $this->assertNull($entry->securityDescriptor()->getDacl());
        $this->assertFalse($entry->isDirty('ntsecuritydescriptor'));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_partial_writes_do_not_mark_assigned_but_unwritten_sections_as_saved()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setDacl(new Acl);
        $replacement = (new SecurityDescriptor)->setOwner(Sid::EVERYONE)
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::SELF)));
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modReplace')->once()->with('cn=Jane', ['ntsecuritydescriptor' => [$replacement->toBinary()]])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->ntSecurityDescriptor = $replacement;

        $entry->saveSecurityDescriptor($replacement);

        $saved = new SecurityDescriptor($entry->getRawOriginal('ntsecuritydescriptor')[0]);
        $this->assertSame(Sid::SELF, (string) $saved->getOwner());
        $this->assertSame(Sid::EVERYONE, (string) $entry->securityDescriptor()->getOwner());
        $this->assertSame($replacement->getDacl()->toBinary(), $saved->getDacl()->toBinary());
        $this->assertTrue($entry->isDirty('ntsecuritydescriptor'));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_failed_control_application_cannot_write_an_unscoped_descriptor()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnFalse(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);

        try {
            $entry->saveSecurityDescriptor($descriptor);
            $this->fail('The control should fail.');
        } catch (LdapRecordException $e) {
            $this->assertSame('Unable to apply the security descriptor control.', $e->getMessage());
        }

        $this->assertNull($entry->securityDescriptor());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_saving_a_new_entry_throws_the_existing_model_exception()
    {
        $this->expectException(ModelDoesNotExistException::class);

        (new Entry)->saveSecurityDescriptor(new SecurityDescriptor);
    }

    public function test_saving_invalid_parts_does_not_change_local_state()
    {
        Container::addConnection(new Connection([], new LdapFake));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);

        $this->expectException(InvalidArgumentException::class);

        $entry->saveSecurityDescriptor(new SecurityDescriptor, 0);
    }
}
