<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Tests\TestCase;

class AccountControlTest extends TestCase
{
    public function test_default_value_is_zero()
    {
        $ac = new AccountControl;

        $this->assertEquals(0, $ac->getValue());
        $this->assertIsInt($ac->getValue());
    }

    public function test_all_options_are_applied_correctly()
    {
        $ac = new AccountControl;

        $values = array_values($ac->getAllFlags());

        $ac
            ->setAccountIsLocked()
            ->setAccountRequiresSmartCard()
            ->setAccountIsTemporary()
            ->setAccountIsForServer()
            ->setAccountIsForInterdomain()
            ->setAccountIsForWorkstation()
            ->setAccountDoesNotRequirePreAuth()
            ->setAccountIsDisabled()
            ->setAccountIsMnsLogon()
            ->setAccountIsNormal()
            ->setAccountIsReadOnly()
            ->setAllowEncryptedTextPassword()
            ->setHomeFolderIsRequired()
            ->setPasswordCannotBeChanged()
            ->setPasswordDoesNotExpire()
            ->setPasswordIsExpired()
            ->setPasswordIsNotRequired()
            ->setRunLoginScript()
            ->setTrustForDelegation()
            ->setTrustToAuthForDelegation()
            ->setDoNotTrustForDelegation()
            ->setUseDesKeyOnly();

        $this->assertEquals(array_sum($values), $ac->getValue());

        $this->assertEquals([
            'SCRIPT' => 1,
            'ACCOUNTDISABLE' => 2,
            'HOMEDIR_REQUIRED' => 8,
            'LOCKOUT' => 16,
            'PASSWD_NOTREQD' => 32,
            'PASSWD_CANT_CHANGE' => 64,
            'ENCRYPTED_TEXT_PWD_ALLOWED' => 128,
            'TEMP_DUPLICATE_ACCOUNT' => 256,
            'NORMAL_ACCOUNT' => 512,
            'INTERDOMAIN_TRUST_ACCOUNT' => 2048,
            'WORKSTATION_TRUST_ACCOUNT' => 4096,
            'SERVER_TRUST_ACCOUNT' => 8192,
            'DONT_EXPIRE_PASSWORD' => 65536,
            'MNS_LOGON_ACCOUNT' => 131072,
            'SMARTCARD_REQUIRED' => 262144,
            'TRUSTED_FOR_DELEGATION' => 524288,
            'NOT_DELEGATED' => 1048576,
            'USE_DES_KEY_ONLY' => 2097152,
            'DONT_REQ_PREAUTH' => 4194304,
            'PASSWORD_EXPIRED' => 8388608,
            'TRUSTED_TO_AUTH_FOR_DELEGATION' => 16777216,
            'PARTIAL_SECRETS_ACCOUNT' => 67108864,
        ], $ac->getAppliedFlags());
    }

    public function test_can_be_casted_to_int()
    {
        $ac = new AccountControl;

        $this->assertEquals(0, $ac->__toInt());
        $this->assertEquals(0, $ac->getValue());
        $this->assertIsInt($ac->__toInt());
    }

    public function test_can_be_casted_to_string()
    {
        $ac = new AccountControl;

        $this->assertEquals('0', (string) $ac);
        $this->assertEquals('0', $ac->__toString());
        $this->assertIsString($ac->__toString());
    }

    public function test_multiple_flags_can_be_applied()
    {
        $flag = 522;

        $ac = new AccountControl($flag);

        $this->assertEquals([
            2 => 2,
            8 => 8,
            512 => 512,
        ], $ac->getFlags());
        $this->assertEquals($flag, $ac->getValue());
    }

    public function test_has()
    {
        $ac = new AccountControl;

        $ac
            ->setAccountIsLocked()
            ->setPasswordDoesNotExpire();

        $this->assertTrue($ac->hasFlag(AccountControl::LOCKOUT));
        $this->assertTrue($ac->hasFlag(AccountControl::DONT_EXPIRE_PASSWORD));
        $this->assertFalse($ac->hasFlag(AccountControl::ACCOUNTDISABLE));
        $this->assertFalse($ac->hasFlag(AccountControl::ENCRYPTED_TEXT_PWD_ALLOWED));
        $this->assertFalse($ac->hasFlag(AccountControl::NORMAL_ACCOUNT));
        $this->assertFalse($ac->hasFlag(AccountControl::PASSWD_NOTREQD));
    }

    public function test_doesnt_have()
    {
        $ac = new AccountControl;

        $ac
            ->setAccountIsLocked()
            ->setPasswordDoesNotExpire();

        $this->assertFalse($ac->doesntHaveFlag(AccountControl::LOCKOUT));
        $this->assertFalse($ac->doesntHaveFlag(AccountControl::DONT_EXPIRE_PASSWORD));
        $this->assertTrue($ac->doesntHaveFlag(AccountControl::ACCOUNTDISABLE));
        $this->assertTrue($ac->doesntHaveFlag(AccountControl::ENCRYPTED_TEXT_PWD_ALLOWED));
        $this->assertTrue($ac->doesntHaveFlag(AccountControl::NORMAL_ACCOUNT));
        $this->assertTrue($ac->doesntHaveFlag(AccountControl::PASSWD_NOTREQD));
    }

    public function test_values_are_overwritten()
    {
        $ac = new AccountControl;

        $ac->setAccountIsNormal()
            ->setAccountIsNormal()
            ->setAccountIsNormal();

        $this->assertEquals(AccountControl::NORMAL_ACCOUNT, $ac->getValue());
    }

    public function test_values_can_be_set()
    {
        $ac = new AccountControl;

        $ac->setAccountIsNormal()->setAccountIsDisabled();

        $values = $ac->getFlags();

        unset($values[AccountControl::ACCOUNTDISABLE]);

        $ac->setFlags($values);

        $this->assertEquals(AccountControl::NORMAL_ACCOUNT, $ac->getValue());
    }

    public function test_values_can_be_added()
    {
        $ac = new AccountControl;

        // Values are overwritten.
        $ac->setFlag(AccountControl::ACCOUNTDISABLE);
        $ac->setFlag(AccountControl::ACCOUNTDISABLE);

        $this->assertEquals(AccountControl::ACCOUNTDISABLE, $ac->getValue());
    }

    public function test_values_can_be_removed()
    {
        $ac = new AccountControl;

        $ac->setAccountIsNormal()->setAccountIsDisabled();

        $ac->unsetFlag(AccountControl::ACCOUNTDISABLE);

        $this->assertEquals(AccountControl::NORMAL_ACCOUNT, $ac->getValue());

        $ac->unsetFlag(AccountControl::NORMAL_ACCOUNT);
        $ac->unsetFlag(AccountControl::NORMAL_ACCOUNT);
        $this->assertEquals(0, $ac->getValue());
    }
}
