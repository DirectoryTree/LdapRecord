<?php

namespace LdapRecord\Tests\Unit\Models\OpenLDAP;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Password;
use LdapRecord\Models\Events\Updated;
use LdapRecord\Models\Events\Updating;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class UserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);
    }

    protected function tearDown(): void
    {
        Container::flush();

        parent::tearDown();
    }

    public function test_settings_users_password_uses_ssha_algo()
    {
        $user = new OpenLDAPUserTestStub;

        $user->password = 'secret';

        $hashedPassword = $user->getModifications()[0]['values'][0];

        $this->assertEquals('SSHA', Password::getHashMethod($hashedPassword));
    }

    public function test_algo_is_automatically_detected_when_changing_a_users_password()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'userpassword' => [
                '{MD5}Xr4ilOzQ4PCOq3aQ0qbuaQ==',
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        [$old, $new] = $user->getModifications();

        $this->assertEquals('MD5', Password::getHashMethod($old['values'][0]));
        $this->assertEquals('MD5', Password::getHashMethod($new['values'][0]));

        $this->assertEquals('{MD5}6Kr5FZqDbwmgv+SEBnfifw==', $new['values'][0]);
    }

    public function test_algo_and_salt_is_automatically_detected_when_changing_a_users_password()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'userpassword' => [
                Password::sha512crypt('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        [$old, $new] = $user->getModifications();

        $this->assertEquals('CRYPT', Password::getHashMethod($old['values'][0]));
        $this->assertEquals('CRYPT', Password::getHashMethod($new['values'][0]));

        [, $oldAlgo] = Password::getHashMethodAndAlgo($old['values'][0]);
        $this->assertEquals(Password::CRYPT_SALT_TYPE_SHA512, $oldAlgo);

        [, $newAlgo] = Password::getHashMethodAndAlgo($new['values'][0]);
        $this->assertEquals(Password::CRYPT_SALT_TYPE_SHA512, $newAlgo);
    }

    public function test_changing_argon2_password_defers_a_pending_change_instead_of_queuing_modifications()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        // The change is performed via an extended operation on save, so no
        // batch modifications are queued for it.
        $this->assertEmpty($user->getModifications());
        $this->assertTrue($user->hasPendingPasswordChange());
    }

    public function test_resetting_argon2_password_still_queues_a_single_replace_modification()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = 'new-secret';

        $modifications = $user->getModifications();

        $this->assertFalse($user->hasPendingPasswordChange());
        $this->assertCount(1, $modifications);
        $this->assertEquals(LDAP_MODIFY_BATCH_REPLACE, $modifications[0]['modtype']);
        $this->assertEquals('ARGON2', Password::getHashMethod($modifications[0]['values'][0]));
        $this->assertStringContainsString('$argon2id$', $modifications[0]['values'][0]);
    }

    public function test_reassigning_a_password_clears_a_pending_password_change()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        $this->assertTrue($user->hasPendingPasswordChange());

        $user->password = 'reset-secret';

        // The reset supersedes the deferred change entirely — only
        // the replace modification may reach the directory on save.
        $this->assertFalse($user->hasPendingPasswordChange());
        $this->assertCount(1, $user->getModifications());
    }

    public function test_discarding_changes_clears_a_pending_password_change()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        $user->discardChanges();

        $this->assertFalse($user->hasPendingPasswordChange());
    }

    public function test_a_pending_password_change_survives_serialization()
    {
        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        $restored = unserialize(serialize($user));

        $this->assertTrue($restored->hasPendingPasswordChange());
    }

    public function test_changing_argon2_password_throws_when_model_does_not_exist()
    {
        $user = new OpenLDAPUserTestStub([
            'userpassword' => Password::argon2id('secret'),
        ]);

        $this->expectException(LdapRecordException::class);

        $user->password = ['secret', 'new-secret'];
    }

    public function test_changing_argon2_password_performs_a_self_service_exop_on_save()
    {
        $ldap = DirectoryFake::setup()->getLdapConnection();

        $ldap->expect([
            LdapFake::operation('bind')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'secret')
                ->andReturnResponse(),

            LdapFake::operation('exopPasswd')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'secret', 'new-secret')
                ->andReturnTrue(),
        ]);

        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        $user->save();

        $this->assertFalse($user->hasPendingPasswordChange());

        // Guards against the change being silently skipped because no batch
        // modifications exist (the early return in Model::performUpdate).
        $ldap->assertMinimumExpectationCounts();
    }

    public function test_changing_argon2_password_throws_when_current_password_is_rejected()
    {
        $ldap = DirectoryFake::setup()->getLdapConnection();

        $ldap->expect([
            LdapFake::operation('bind')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'wrong-secret')
                ->andReturnResponse(49),
        ]);

        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['wrong-secret', 'new-secret'];

        $this->expectException(LdapRecordException::class);

        $user->save();
    }

    public function test_changing_argon2_password_fires_update_events_on_save()
    {
        $ldap = DirectoryFake::setup()->getLdapConnection();

        $ldap->expect([
            LdapFake::operation('bind')->once()->andReturnResponse(),
            LdapFake::operation('exopPasswd')->once()->andReturnTrue(),
        ]);

        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        $fired = [];

        $dispatcher = Container::getInstance()->getDispatcher();

        $dispatcher->listen(Updating::class, function () use (&$fired) {
            $fired[] = 'updating';
        });

        $dispatcher->listen(Updated::class, function () use (&$fired) {
            $fired[] = 'updated';
        });

        $user->save();

        $this->assertEquals(['updating', 'updated'], $fired);
    }

    public function test_failed_argon2_password_change_is_retained_and_can_be_retried()
    {
        $ldap = DirectoryFake::setup()->getLdapConnection();

        $ldap->expect([
            LdapFake::operation('bind')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'secret')
                ->andReturnResponse(49),

            LdapFake::operation('bind')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'secret')
                ->andReturnResponse(),

            LdapFake::operation('exopPasswd')->once()
                ->with('cn=jdoe,dc=local,dc=com', 'secret', 'new-secret')
                ->andReturnTrue(),
        ]);

        $user = (new OpenLDAPUserTestStub)->setRawAttributes([
            'dn' => ['cn=jdoe,dc=local,dc=com'],
            'userpassword' => [
                Password::argon2id('secret'),
            ],
        ]);

        $user->password = ['secret', 'new-secret'];

        try {
            $user->save();

            $this->fail('Expected the initial save to throw.');
        } catch (LdapRecordException) {
            // A transiently failed change is retained for retry.
        }

        $this->assertTrue($user->hasPendingPasswordChange());

        $user->save();

        $this->assertFalse($user->hasPendingPasswordChange());
    }

    public function test_correct_auth_identifier_is_returned()
    {
        $entryUuid = 'foo';

        $user = new User(['entryuuid' => $entryUuid]);

        $this->assertEquals($entryUuid, $user->getAuthIdentifier());
    }
}

class OpenLDAPUserTestStub extends User
{
    protected function assertSecureConnection(): void {}
}
