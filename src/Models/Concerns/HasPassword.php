<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Utilities;
use LdapRecord\LdapRecordException;
use LdapRecord\Connections\ConnectionException;
use LdapRecord\Models\UserPasswordPolicyException;
use LdapRecord\Models\UserPasswordIncorrectException;

trait HasPassword
{
    /**
     * The attribute to use for password changes.
     *
     * @var string
     */
    protected $passwordAttribute = 'unicodepwd';

    /**
     * Sets the password on the current user.
     *
     * @param string $password
     *
     * @throws \Exception When no SSL or TLS secured connection is present.
     *
     * @return \LdapRecord\Models\ActiveDirectory\User
     */
    public function setPassword($password)
    {
        $this->validateSecureConnection();

        $encodedPassword = Utilities::encodePassword($password);

        // If the record exists, we need to add a batch replace
        // modification, otherwise we'll receive a "type or
        // value" exists exception from our LDAP server.
        if ($this->exists) {
            return $this->addModification(
                $this->newBatchModification(
                    $this->passwordAttribute,
                    LDAP_MODIFY_BATCH_REPLACE,
                    [$encodedPassword]
                )
            );
        }
        // Otherwise, we are creating a new record
        // and we can set the attribute normally.
        else {
            return $this->setFirstAttribute($this->passwordAttribute, $encodedPassword);
        }
    }

    /**
     * Change the password of the current user. This must be performed over SSL / TLS.
     *
     * Throws an exception on failure.
     *
     * @param string $oldPassword The new password
     * @param string $newPassword The old password
     * @param bool   $replace     Alternative password change method. Set to true if you're receiving 'CONSTRAINT'
     *                                 errors.
     *
     * @throws UserPasswordPolicyException    When the new password does not match your password policy.
     * @throws UserPasswordIncorrectException When the old password is incorrect.
     * @throws LdapRecordException            When an unknown cause of failure occurs.
     *
     * @return true
     */
    public function changePassword($oldPassword, $newPassword, $replace = false)
    {
        $this->validateSecureConnection();

        $modifications = [];

        if ($replace) {
            $modifications[] = $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [Utilities::encodePassword($newPassword)]
            );
        } else {
            // Create batch modification for removing the old password.
            $modifications[] = $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [Utilities::encodePassword($oldPassword)]
            );

            // Create batch modification for adding the new password.
            $modifications[] = $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_ADD,
                [Utilities::encodePassword($newPassword)]
            );
        }

        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        try {
            return $this->update();
        } catch (\Exception $ex) {
            $connection = $this->newQuery()->getConnection();

            $code = $connection->getExtendedErrorCode();

            switch ($code) {
                case '0000052D':
                    throw new UserPasswordPolicyException(
                        "Error: $code. Your new password does not match the password policy."
                    );
                case '00000056':
                    throw new UserPasswordIncorrectException(
                        "Error: $code. Your old password is incorrect."
                    );
                default:
                    throw new LdapRecordException($connection->getExtendedError(), $code, $ex);
            }
        }
    }

    /**
     * Validates that the current LDAP connection is secure.
     *
     * @throws ConnectionException
     *
     * @return void
     */
    protected function validateSecureConnection()
    {
        if (!$this->getConnection()->getLdapConnection()->canChangePasswords()) {
            throw new ConnectionException(
                'You must be connected to your LDAP server with TLS or SSL to perform this operation.'
            );
        }
    }
}