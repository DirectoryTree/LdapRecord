<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Utilities;
use LdapRecord\ConnectionException;

trait HasPassword
{
    /**
     * The attribute to use for password changes.
     *
     * @var string
     */
    protected $passwordAttribute = 'unicodepwd';

    /**
     * Set the password on the user.
     *
     * @param string|array $password
     *
     * @throws \LdapRecord\ConnectionException
     */
    public function setUnicodepwdAttribute($password)
    {
        $this->validateSecureConnection();

        // If the password given is an array, we can assume we
        // are changing the password for the current user.
        if (is_array($password)) {
            $this->setChangedPassword(
                $this->getEncodedPassword($password[0]),
                $this->getEncodedPassword($password[1])
            );
        }
        // Otherwise, we will set the password normally.
        else {
            $this->setPassword($this->getEncodedPassword($password));
        }
    }

    /**
     * Set the changed password.
     *
     * @param $oldPassword
     * @param $newPassword
     */
    protected function setChangedPassword($oldPassword, $newPassword)
    {
        // Create batch modification for removing the old password.
        $modifications[] = $this->newBatchModification(
            $this->passwordAttribute,
            LDAP_MODIFY_BATCH_REMOVE,
            [$oldPassword]
        );

        // Create batch modification for adding the new password.
        $modifications[] = $this->newBatchModification(
            $this->passwordAttribute,
            LDAP_MODIFY_BATCH_ADD,
            [$newPassword]
        );

        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }
    }

    /**
     * Set the password on the model.
     *
     * @param string $encodedPassword
     */
    protected function setPassword($encodedPassword)
    {
        return $this->attributes[$this->passwordAttribute] = [$encodedPassword];
    }

    /**
     * Encode the given password.
     *
     * @param string $password
     *
     * @return string
     */
    protected function getEncodedPassword($password)
    {
        return Utilities::encodePassword($password);
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
