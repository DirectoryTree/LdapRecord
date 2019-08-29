<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use Exception;
use Mockery as m;
use LdapRecord\Utilities;
use LdapRecord\Tests\TestCase;
use LdapRecord\Connections\Ldap;
use LdapRecord\Connections\Container;
use LdapRecord\Connections\Connection;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Connections\ConnectionException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Models\UserPasswordPolicyException;
use LdapRecord\Models\UserPasswordIncorrectException;

class UserTest extends TestCase
{
    public function test_setting_password_requires_secure_connection()
    {
        Container::getInstance()->add(new Connection());

        $this->expectException(ConnectionException::class);

        $user = new User();
        $user->setPassword('foo');
    }

    public function test_changing_password_requires_secure_connection()
    {
        Container::getInstance()->add(new Connection());

        $this->expectException(ConnectionException::class);

        $user = (new User())->setRawAttributes(['dn' => 'foo']);
        $user->changePassword('foo', 'bar');
    }

    public function test_set_password_on_new_user()
    {
        $user = new UserPasswordTestStub();
        $user->setPassword('foo');
        $this->assertEquals(Utilities::encodePassword('foo'), $user->getFirstAttribute('unicodepwd'));
    }

    public function test_set_password_on_existing_user()
    {
        $user = (new UserPasswordTestStub())->setRawAttributes(['dn' => 'foo']);
        $user->setPassword('bar');

        $expected = [
            [
                'attrib'  => 'unicodepwd',
                'modtype' => 3,
                'values'  => [Utilities::encodePassword('bar')],
            ],
        ];

        $this->assertEquals($expected, $user->getModifications());
    }

    public function test_change_password()
    {
        $user = (new UserPasswordTestStub())->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($user->changePassword('bar', 'baz'));

        $this->assertEquals([
            [
                'attrib'  => 'unicodepwd',
                'modtype' => 2,
                'values'  => [Utilities::encodePassword('bar')],
            ],
            [
                'attrib'  => 'unicodepwd',
                'modtype' => 1,
                'values'  => [Utilities::encodePassword('baz')],
            ],
        ], $user->getModifications());
    }

    public function test_change_password_with_replace()
    {
        $user = (new UserPasswordTestStub())->setRawAttributes(['dn' => 'foo']);
        $this->assertTrue($user->changePassword('bar', 'baz', $replace = true));

        $this->assertEquals([
            [
                'attrib'  => 'unicodepwd',
                'modtype' => 3,
                'values'  => [Utilities::encodePassword('baz')],
            ],
        ], $user->getModifications());
    }

    public function test_change_password_policy_failure()
    {
        $ldap = m::mock(Ldap::class);
        $ldap->shouldReceive('getExtendedErrorCode')->once()->andReturn('0000052D');

        $conn = m::mock(Connection::class);
        $conn->shouldReceive('getConfiguration')->once()->andReturn(new DomainConfiguration());
        $conn->shouldReceive('getLdapConnection')->once()->andReturn($ldap);

        Container::getInstance()->add($conn);

        $user = (new UserPasswordChangeFailureTestStub())->setRawAttributes(['dn' => 'foo']);

        $this->expectException(UserPasswordPolicyException::class);
        $user->changePassword('bar', 'baz');
    }

    public function test_change_password_old_password_incorrect_failure()
    {
        $ldap = m::mock(Ldap::class);
        $ldap->shouldReceive('getExtendedErrorCode')->once()->andReturn('00000056');

        $conn = m::mock(Connection::class);
        $conn->shouldReceive('getConfiguration')->once()->andReturn(new DomainConfiguration());
        $conn->shouldReceive('getLdapConnection')->once()->andReturn($ldap);

        Container::getInstance()->add($conn);

        $user = (new UserPasswordChangeFailureTestStub())->setRawAttributes(['dn' => 'foo']);

        $this->expectException(UserPasswordIncorrectException::class);
        $user->changePassword('bar', 'baz');
    }
}

class UserPasswordTestStub extends User
{
    public function update(array $attributes = [])
    {
        return true;
    }

    protected function validateSecureConnection()
    {
        return true;
    }
}

class UserPasswordChangeFailureTestStub extends User
{
    public function update(array $attributes = [])
    {
        throw new Exception();
    }

    protected function validateSecureConnection()
    {
        return true;
    }
}
