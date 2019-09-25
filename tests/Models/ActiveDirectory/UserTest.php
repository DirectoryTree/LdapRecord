<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use LdapRecord\Utilities;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Tests\TestCase;
use LdapRecord\ConnectionException;
use LdapRecord\Models\ActiveDirectory\User;

class UserTest extends TestCase
{
    public function test_setting_password_requires_secure_connection()
    {
        Container::getInstance()->add(new Connection());

        $this->expectException(ConnectionException::class);

        new User(['unicodepwd' => 'password']);
    }

    public function test_changing_password_requires_secure_connection()
    {
        Container::getInstance()->add(new Connection());

        $this->expectException(ConnectionException::class);

        $user = (new User())->setRawAttributes(['dn' => 'foo']);
        $user->unicodepwd = ['old', 'new'];
    }

    public function test_set_password_on_new_user()
    {
        $user = new UserPasswordTestStub();
        $user->unicodepwd = 'foo';
        $this->assertEquals([Utilities::encodePassword('foo')], $user->unicodepwd);
    }

    public function test_changing_passwords()
    {
        $user = (new UserPasswordTestStub())->setRawAttributes(['dn' => 'foo']);
        $user->unicodepwd = ['bar', 'baz'];

        $this->assertEquals([
            [
                'attrib' => 'unicodepwd',
                'modtype' => 2,
                'values' => [Utilities::encodePassword('bar')],
            ],
            [
                'attrib' => 'unicodepwd',
                'modtype' => 1,
                'values' => [Utilities::encodePassword('baz')],
            ],
        ], $user->getModifications());
    }
}

class UserPasswordTestStub extends User
{
    protected function validateSecureConnection()
    {
        return true;
    }
}
