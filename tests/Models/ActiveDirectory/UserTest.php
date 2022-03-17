<?php

namespace LdapRecord\Tests\Models\ActiveDirectory;

use Carbon\Carbon;
use LdapRecord\Connection;
use LdapRecord\ConnectionException;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Scopes\RejectComputerObjectClass;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\Password;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Tests\TestCase;

class UserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection());
    }

    protected function tearDown(): void
    {
        Container::reset();

        parent::tearDown();
    }

    public function test_setting_password_requires_secure_connection()
    {
        $this->expectException(ConnectionException::class);

        new User(['unicodepwd' => 'password']);
    }

    public function test_changing_password_requires_secure_connection()
    {
        $user = (new User())->setRawAttributes(['dn' => 'foo']);

        $this->expectException(ConnectionException::class);

        $user->unicodepwd = ['old', 'new'];
    }

    public function test_set_password_on_new_user()
    {
        $user = new UserPasswordTestStub();

        $user->unicodepwd = 'foo';

        $this->assertEquals([Password::encode('foo')], $user->getModifications()[0]['values']);
    }

    public function test_password_mutator_alias_works()
    {
        $user = new UserPasswordTestStub(['password' => 'secret']);

        $this->assertEquals([Password::encode('secret')], $user->getModifications()[0]['values']);
    }

    public function test_changing_passwords()
    {
        $user = (new UserPasswordTestStub())->setRawAttributes(['dn' => 'foo']);

        $user->unicodepwd = ['bar', 'baz'];

        $this->assertEquals([
            [
                'attrib' => 'unicodepwd',
                'modtype' => 2,
                'values' => [Password::encode('bar')],
            ],
            [
                'attrib' => 'unicodepwd',
                'modtype' => 1,
                'values' => [Password::encode('baz')],
            ],
        ], $user->getModifications());
    }

    public function test_reject_computer_object_class_is_a_default_scope()
    {
        $this->assertInstanceOf(RejectComputerObjectClass::class, (new User())->getGlobalScopes()[RejectComputerObjectClass::class]);
    }

    public function test_scope_where_has_mailbox_is_applied()
    {
        $filters = User::whereHasMailbox()->filters;

        $this->assertEquals($filters['and'][4]['field'], 'msExchMailboxGuid');
        $this->assertEquals($filters['and'][4]['operator'], '*');
    }

    public function test_scope_where_has_lockout_is_applied()
    {
        $filters = User::whereHasLockout()->filters;

        $this->assertEquals($filters['and'][4]['field'], 'lockoutTime');
        $this->assertEquals($filters['and'][4]['operator'], '>=');
        $this->assertEquals($filters['and'][4]['value']->raw(), 1);
    }

    public function test_is_locked_out()
    {
        $lockoutTime = (new Timestamp('windows-int'))->fromDateTime(
            Carbon::now()->subMinutes(10)
        );

        $user = (new User)->setRawAttributes(
            ['lockouttime' => [$lockoutTime]]
        );

        $this->assertTrue($user->isLockedOut('UTC', $lockoutDuration = 11));
        $this->assertFalse($user->isLockedOut('UTC', $lockoutDuration = 10));
    }

    public function test_is_locked_out_with_only_duration()
    {
        $lockoutTime = (new Timestamp('windows-int'))->fromDateTime(
            Carbon::now()->subMinutes(10)
        );

        $user = (new User)->setRawAttributes(
            ['lockouttime' => [$lockoutTime]]
        );

        $this->assertTrue($user->isLockedOut(null, $lockoutDuration = 11));
        $this->assertFalse($user->isLockedOut(null, $lockoutDuration = 10));
    }
}

class UserPasswordTestStub extends User
{
    protected function validateSecureConnection()
    {
        return true;
    }
}
