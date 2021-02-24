<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\ConnectionException;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Password;

trait HasPassword
{
    /**
     * The attribute to use for password changes.
     *
     * @var string
     */
    protected $passwordAttribute = 'unicodepwd';

    /**
     * The method to use for hashing / encoding user passwords.
     *
     * @var string
     */
    protected $passwordHashMethod = 'encode';

    /**
     * Set the password on the user.
     *
     * @param string|array $password
     *
     * @throws ConnectionException
     */
    public function setPasswordAttribute($password)
    {
        $this->validateSecureConnection();
        $this->setCurrentPasswordHashMethod();

        // If the password given is an array, we can assume we
        // are changing the password for the current user.
        if (is_array($password)) {
            $this->setChangedPassword(
                $this->getHashedPassword($password[0], $this->getPasswordSalt()),
                $this->getHashedPassword($password[1])
            );
        }
        // Otherwise, we will assume the password is being
        // reset, overwriting the one currently in place.
        else {
            $this->setPassword($this->getHashedPassword($password));
        }
    }

    /**
     * Alias for setting the password on the user.
     *
     * @param string|array $password
     *
     * @throws ConnectionException
     */
    public function setUnicodepwdAttribute($password)
    {
        $this->setPasswordAttribute($password);
    }

    /**
     * Set the changed password.
     *
     * @param string $oldPassword
     * @param string $newPassword
     *
     * @return void
     */
    protected function setChangedPassword($oldPassword, $newPassword)
    {
        // Create batch modification for removing the old password.
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [$oldPassword]
            )
        );

        // Create batch modification for adding the new password.
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_ADD,
                [$newPassword]
            )
        );
    }

    /**
     * Set the password on the model.
     *
     * @param string $password
     *
     * @return void
     */
    protected function setPassword($password)
    {
        $this->addModification(
            $this->newBatchModification(
                $this->passwordAttribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [$password]
            )
        );
    }

    /**
     * Encode / hash the given password.
     *
     * @param string $password
     * @param string $salt
     *
     * @return string
     *
     * @throws LdapRecordException
     */
    protected function getHashedPassword($password, $salt = null)
    {
        if (! method_exists(Password::class, $this->passwordHashMethod)) {
            throw new LdapRecordException("Password hashing method [{$this->passwordHashMethod}] does not exist.");
        }

        if(Password::isSalted($this->passwordHashMethod)) {
            return Password::{$this->passwordHashMethod}($password, $salt);
        }       

        return Password::{$this->passwordHashMethod}($password);
    }

    /**
     * Validates that the current LDAP connection is secure.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    protected function validateSecureConnection()
    {
        if (! $this->getConnection()->getLdapConnection()->canChangePasswords()) {
            throw new ConnectionException(
                'You must be connected to your LDAP server with TLS or SSL to perform this operation.'
            );
        }
    }

    /**
     * If user password is salted method return the salt.
     *
     * @return null|string
     */
    public function getPasswordSalt() {
        if(Password::isSalted($this->passwordHashMethod))
            return Password::getSalt($this->getAttributes()[$this->passwordAttribute][0]);
        return null;
    }

    /**
     * @inheritdoc
     */
    public function setCurrentPasswordHashMethod() 
    { 
        if(count($this->getAttributes($this->passwordAttribute)) <= 0) return;

        if (preg_match( '/^\{(\w+)\}/', $this->getAttributes()[$this->passwordAttribute][0], $matches)) {

            if($matches[1] == "CRYPT") {

                if(preg_match('/^\{(\w+)\}\$([0-9a-z]{1})\$/', $this->getAttributes()[$this->passwordAttribute][0], $mc)) {
                   
                    switch($mc[2]) {
                        case "1":
                            $this->passwordHashMethod = "MD5" . $mc[1];
                            return $this;
                        break;
                        case "5": 
                            $this->passwordHashMethod = "SHA256" . $mc[1];
                            return $this;
                        break;
                        case "6":
                            $this->passwordHashMethod = "SHA512" . $mc[1];
                            return $this;
                        break;
                        default:
                            throw new LdapRecordException("Crypt algorithm not supported");
                        break;    
                    }
                }

                $this->passwordHashMethod = $matches[1];

            } else {

                $this->passwordHashMethod = $matches[1];
            }

            if (! method_exists(Password::class, $this->passwordHashMethod)) {
                throw new LdapRecordException("Password hashing method [{$this->passwordHashMethod}] does not exist.");
            }

            return $this;          
        }

        throw new LdapRecordException("Unable to automatically detect password hash method");       
    }

    public function getPasswordHashMethod()
    {
        return $this->passwordHashMethod;
    }
}
