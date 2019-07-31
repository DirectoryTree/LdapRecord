<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Utilities;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Concerns\HasMemberOf;
use LdapRecord\Models\UserPasswordPolicyException;
use LdapRecord\Models\UserPasswordIncorrectException;
use Illuminate\Contracts\Auth\Authenticatable;

class User extends Entry implements Authenticatable
{
    use HasMemberOf;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
    ];

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return $this->guidKey;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getConvertedGuid();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param string $value
     *
     * @return void
     */
    public function setRememberToken($value)
    {
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
    }

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'member');
    }

    /**
     * Retrieves the primary group of the current user.
     *
     * @return Group|bool
     */
    public function getPrimaryGroup()
    {
        $groupSid = preg_replace('/\d+$/', $this->getFirstAttribute('primarygroupid'), $this->getConvertedSid());

        return $this->newQueryWithoutScopes()->setModel(new Group())->findBySid($groupSid);
    }

    /**
     * Sets the password on the current user.
     *
     * @param string $password
     *
     * @throws LdapRecordException When no SSL or TLS secured connection is present.
     *
     * @return \LdapRecord\Models\ActiveDirectory\User
     */
    public function setPassword($password)
    {
        $this->validateSecureConnection();

        $encodedPassword = Utilities::encodePassword($password);

        if ($this->exists) {
            // If the record exists, we need to add a batch replace
            // modification, otherwise we'll receive a "type or
            // value" exists exception from our LDAP server.
            return $this->addModification(
                $this->newBatchModification(
                    'unicodepwd',
                    LDAP_MODIFY_BATCH_REPLACE,
                    [$encodedPassword]
                )
            );
        } else {
            // Otherwise, we are creating a new record
            // and we can set the attribute normally.
            return $this->setFirstAttribute(
                'unicodepwd',
                $encodedPassword
            );
        }
    }

    /**
     * Change the password of the current user. This must be performed over SSL / TLS.
     *
     * Throws an exception on failure.
     *
     * @param string $oldPassword      The new password
     * @param string $newPassword      The old password
     * @param bool   $replaceNotRemove Alternative password change method. Set to true if you're receiving 'CONSTRAINT'
     *                                 errors.
     *
     * @throws UserPasswordPolicyException    When the new password does not match your password policy.
     * @throws UserPasswordIncorrectException When the old password is incorrect.
     * @throws LdapRecordException            When an unknown cause of failure occurs.
     *
     * @return true
     */
    public function changePassword($oldPassword, $newPassword, $replaceNotRemove = false)
    {
        $this->validateSecureConnection();

        $attribute = 'unicodepwd';

        $modifications = [];

        if ($replaceNotRemove) {
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [Utilities::encodePassword($newPassword)]
            );
        } else {
            // Create batch modification for removing the old password.
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [Utilities::encodePassword($oldPassword)]
            );

            // Create batch modification for adding the new password.
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_ADD,
                [Utilities::encodePassword($newPassword)]
            );
        }

        // Add the modifications.
        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        $result = @$this->update();

        if (!$result) {
            // If the user failed to update, we'll see if we can
            // figure out why by retrieving the extended error.
            $error = $this->query->getConnection()->getExtendedError();
            $code = $this->query->getConnection()->getExtendedErrorCode();

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
                    throw new LdapRecordException($error);
            }
        }

        return $result;
    }
}
