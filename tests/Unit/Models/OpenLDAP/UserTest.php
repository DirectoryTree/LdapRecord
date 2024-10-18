<?php

namespace LdapRecord\Tests\Unit\Models\OpenLDAP;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Attributes\Password;
use LdapRecord\Models\OpenLDAP\User;
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
