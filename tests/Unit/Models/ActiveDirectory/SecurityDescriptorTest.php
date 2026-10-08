<?php

namespace LdapRecord\Tests\Unit\Models\ActiveDirectory;

use InvalidArgumentException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Events\DispatcherInterface;
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

    public function test_save_writes_permissions_and_other_attributes_together_with_the_usual_events()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl(new Acl)->setSacl((new Acl)->addAce(Ace::audit(Sid::SELF)))
            ->setResourceManagerControl(0xAB)->setControlFlags(0xE014);
        $replacement = (new SecurityDescriptor($original->toBinary()))
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::EVERYONE, Ace::CONTROL_ACCESS)))
            ->setControlFlags(0xF014);
        $previous = [
            ['oid' => '1.2.3', 'isCritical' => false, 'value' => null],
            ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020107')],
        ];
        $controls = [
            $previous[0],
            ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')],
        ];
        $modifications = [
            ['attrib' => 'cn', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['Janet']],
            ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$replacement->toBinary()]],
        ];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn($previous),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', $modifications)->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $previous)->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updated::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Saved::class)->ordered();
        Container::getInstance()->setDispatcher($dispatcher);
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'cn' => ['Jane'], 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->cn = 'Janet';
        $entry->ntSecurityDescriptor = $replacement;

        $entry->save();

        $saved = $entry->securityDescriptor();
        $this->assertSame(Sid::SELF, (string) $saved->getOwner());
        $this->assertSame(Sid::SELF, (string) $saved->getGroup());
        $this->assertSame($original->getSacl()->toBinary(), $saved->getSacl()->toBinary());
        $this->assertSame(Ace::ACCESS_DENIED, $saved->getDacl()->getAces()->first()->getType());
        $this->assertSame(0xAB, $saved->getResourceManagerControl());
        $this->assertSame(0xF014, $saved->getControlFlags());
        $this->assertSame(['Janet'], $entry->getRawOriginal('cn'));
        $this->assertSame([$replacement->toBinary()], $entry->getRawOriginal('ntsecuritydescriptor'));
        $this->assertSame([], $entry->getDirty());
        $this->assertTrue($entry->wasChanged(['cn', 'ntsecuritydescriptor']));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_detects_owner_and_audit_changes_without_selecting_the_dacl_or_group()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl((new Acl)->addAce(Ace::allow(Sid::SELF)))->setSacl(new Acl);
        $replacement = (new SecurityDescriptor($original->toBinary()))->setOwner(Sid::EVERYONE)
            ->setSacl((new Acl)->addAce(Ace::audit(Sid::EVERYONE, Ace::GENERIC_READ)->setFlags(Ace::SUCCESSFUL_ACCESS)));
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020109')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$replacement->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);

        $entry->save(['ntSecurityDescriptor' => $replacement]);

        $this->assertSame($replacement->toBinary(), $entry->getRawOriginal('ntsecuritydescriptor')[0]);
        $this->assertSame(Sid::SELF, (string) $entry->securityDescriptor()->getGroup());
        $this->assertSame($original->getDacl()->toBinary(), $entry->securityDescriptor()->getDacl()->toBinary());
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_detects_changes_to_all_four_sections()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setGroup(Sid::SELF)
            ->setDacl(new Acl)->setSacl(new Acl);
        $replacement = (new SecurityDescriptor)->setOwner(Sid::EVERYONE)->setGroup(Sid::EVERYONE)
            ->setDacl((new Acl)->addAce(Ace::deny(Sid::SELF)))
            ->setSacl((new Acl)->addAce(Ace::audit(Sid::SELF)));
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('300302010f')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$replacement->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->ntSecurityDescriptor = $replacement;

        $entry->save();

        $this->assertSame($replacement->toBinary(), $entry->getRawOriginal('ntsecuritydescriptor')[0]);
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_failed_save_restores_controls_and_keeps_all_changes_pending_without_success_events()
    {
        $previous = [['oid' => '1.2.3', 'isCritical' => false, 'value' => null]];
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $controls = [$previous[0], ['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn($previous),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'cn', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['Janet']],
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$descriptor->toBinary()]],
            ])->andThrow(new LdapRecordException('Insufficient access')),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $previous)->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $dispatcher = m::mock(DispatcherInterface::class);
        $dispatcher->shouldReceive('fire')->once()->with(Saving::class)->ordered();
        $dispatcher->shouldReceive('fire')->once()->with(Updating::class)->ordered();
        Container::getInstance()->setDispatcher($dispatcher);
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'cn' => ['Jane']]);
        $entry->cn = 'Janet';
        $entry->ntSecurityDescriptor = $descriptor;
        $attributes = $entry->getAttributes();
        $original = $entry->getOriginal();

        try {
            $entry->save();
            $this->fail('The write should fail.');
        } catch (LdapRecordException $e) {
            $this->assertSame('Insufficient access', $e->getMessage());
        }

        $this->assertSame($attributes, $entry->getAttributes());
        $this->assertSame($original, $entry->getOriginal());
        $this->assertSame([], $entry->getChanges());
        $this->assertTrue($entry->isDirty('cn'));
        $this->assertTrue($entry->isDirty('ntsecuritydescriptor'));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_preserves_the_distinction_between_null_and_empty_dacls()
    {
        $original = (new SecurityDescriptor)->setDacl(new Acl);
        $replacement = (new SecurityDescriptor)->setDacl(null);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$replacement->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->ntSecurityDescriptor = $replacement;

        $entry->save();

        $this->assertTrue($entry->securityDescriptor()->hasDacl());
        $this->assertNull($entry->securityDescriptor()->getDacl());
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_replaces_a_descriptor_when_the_attribute_was_not_loaded()
    {
        $descriptor = (new SecurityDescriptor)->setDacl((new Acl)->addAce(Ace::deny(Sid::SELF)));
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$descriptor->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);

        $entry->save(['ntSecurityDescriptor' => [$descriptor->toBinary()]]);

        $this->assertSame([$descriptor->toBinary()], $entry->getRawOriginal('ntsecuritydescriptor'));
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_failed_control_application_keeps_changes_pending_and_cannot_write_the_descriptor()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnFalse(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);
        $entry->ntSecurityDescriptor = $descriptor;
        $original = $entry->getOriginal();

        try {
            $entry->save();
            $this->fail('The control should fail.');
        } catch (LdapRecordException $e) {
            $this->assertSame('Unable to apply the security descriptor control.', $e->getMessage());
        }

        $this->assertSame([$descriptor->toBinary()], $entry->getAttribute('ntsecuritydescriptor'));
        $this->assertSame($original, $entry->getOriginal());
        $this->assertTrue($entry->isDirty('ntsecuritydescriptor'));
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_does_not_apply_descriptor_controls_when_only_another_attribute_changes()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'cn', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['Janet']],
            ])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'cn' => ['Jane'], 'ntsecuritydescriptor' => [$descriptor->toBinary()]]);
        $entry->cn = 'Janet';

        $entry->save();

        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_assigning_an_unchanged_descriptor_does_not_issue_a_write()
    {
        Container::addConnection(new Connection([], new LdapFake));
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$descriptor->toBinary()]]);
        $entry->ntSecurityDescriptor = $entry->securityDescriptor();

        $entry->save();

        $this->assertSame([], $entry->getDirty());
        $this->assertSame([], $entry->getChanges());
    }

    public function test_save_quietly_writes_the_descriptor_without_dispatching_events()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020104')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$descriptor->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        Container::getInstance()->setDispatcher(m::mock(DispatcherInterface::class));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane']);

        $entry->saveQuietly(['ntSecurityDescriptor' => $descriptor]);

        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_save_detects_pending_descriptor_sections_after_serialization()
    {
        $original = (new SecurityDescriptor)->setOwner(Sid::SELF)->setDacl(new Acl);
        $replacement = (new SecurityDescriptor($original->toBinary()))->setOwner(Sid::EVERYONE);
        $controls = [['oid' => LdapInterface::OID_SERVER_SD_FLAGS, 'isCritical' => true, 'value' => hex2bin('3003020101')]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('getOption')->once()->with(LDAP_OPT_SERVER_CONTROLS)->andReturn([]),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'ntsecuritydescriptor', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => [$replacement->toBinary()]],
            ])->andReturnTrue(),
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, [])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$original->toBinary()]]);
        $entry->ntSecurityDescriptor = $replacement;
        $entry = unserialize(serialize($entry));

        $entry->save();

        $this->assertSame([$replacement->toBinary()], $entry->getRawOriginal('ntsecuritydescriptor'));
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_normalizing_descriptor_offsets_does_not_rewrite_unchanged_permissions()
    {
        $binary = hex2bin(trim(file_get_contents(__DIR__.'/../Attributes/SecurityDescriptor/fixtures/active-directory.hex')));
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('modifyBatch')->once()->with('cn=Jane', [
                ['attrib' => 'cn', 'modtype' => LDAP_MODIFY_BATCH_REPLACE, 'values' => ['Janet']],
            ])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$binary], 'cn' => ['Jane']]);
        $entry->ntSecurityDescriptor = $entry->securityDescriptor();
        $entry->cn = 'Janet';

        $entry->save();

        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_header_edits_are_not_silently_marked_as_saved()
    {
        Container::addConnection(new Connection([], new LdapFake));
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $entry = (new Entry)->setRawAttributes(['dn' => 'cn=Jane', 'ntsecuritydescriptor' => [$descriptor->toBinary()]]);
        $original = $entry->getOriginal();
        $entry->ntSecurityDescriptor = $entry->securityDescriptor()->setResourceManagerControl(0xAB);

        try {
            $entry->save();
            $this->fail('The header edit should fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only owner, group, DACL, and SACL changes can be saved.', $e->getMessage());
        }

        $this->assertSame($original, $entry->getOriginal());
        $this->assertTrue($entry->isDirty('ntsecuritydescriptor'));
    }

    public function test_save_uses_the_normal_insert_path_for_new_entries_with_a_descriptor()
    {
        $descriptor = (new SecurityDescriptor)->setDacl(new Acl);
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('add')->once()->with('cn=Jane', [
                'cn' => ['Jane'],
                'objectclass' => ['top'],
                'ntsecuritydescriptor' => [$descriptor->toBinary()],
            ])->andReturnTrue(),
        ]);
        Container::addConnection(new Connection([], $ldap));
        $entry = (new Entry(['cn' => 'Jane', 'objectclass' => 'top', 'ntSecurityDescriptor' => $descriptor]))->setDn('cn=Jane');

        $entry->save();

        $this->assertTrue($entry->exists);
        $this->assertTrue($entry->wasRecentlyCreated);
        $this->assertSame([], $entry->getDirty());
        $ldap->assertMinimumExpectationCounts();
    }
}
